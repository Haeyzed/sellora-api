<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\StartSocialSignIn;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInIntent;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Exceptions\LastSignInMethodException;
use App\Tenant\Customers\Exceptions\SocialIdentityInUseException;
use App\Tenant\Customers\Exceptions\SocialProviderAlreadyLinkedException;
use App\Tenant\Customers\Exceptions\SocialProviderNotOfferedException;
use App\Tenant\Customers\Exceptions\SocialSignInFailedException;
use App\Tenant\Customers\Http\Requests\DisconnectSocialIdentityRequest;
use App\Tenant\Customers\Http\Requests\LinkSocialIdentityRequest;
use App\Tenant\Customers\Http\Requests\StartSocialSignInRequest;
use App\Tenant\Customers\Http\Resources\CustomerIdentityResource;
use App\Tenant\Customers\Http\Resources\SocialAuthorizationResource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\SocialAccountLinks;
use App\Tenant\Customers\Services\SocialSignInAttempts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use LogicException;

/**
 * The providers the signed-in customer signs in with (Step 10b): listing, connecting and disconnecting them.
 */
final class CustomerIdentityController extends Controller
{
    /**
     * List my sign-in providers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CustomerIdentityResource::collection($this->customer($request)->identities()->orderBy('id')->get());
    }

    /**
     * Start connecting a provider.
     *
     * Returns the provider's address to send the browser to; finish with
     * "connect a provider" once it comes back to the storefront's callback.
     *
     * @throws SocialProviderNotOfferedException
     */
    public function start(StartSocialSignInRequest $request, SocialProvider $provider, StartSocialSignIn $startSocialSignIn): JsonResponse
    {
        return (new SocialAuthorizationResource($startSocialSignIn->handle($provider, SocialSignInIntent::Link, $this->customer($request), $request->returnPath())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Connect a provider.
     *
     * With the state and code from the storefront's callback, for a connection
     * you started. Never changes your account's email. A provider account
     * already connected to someone else at this store is refused.
     *
     * @throws InvalidSocialSignInException
     * @throws SocialSignInFailedException
     * @throws SocialIdentityInUseException
     * @throws SocialProviderAlreadyLinkedException
     */
    public function store(LinkSocialIdentityRequest $request, SocialProvider $provider, SocialSignInAttempts $socialSignInAttempts, SocialAccountLinks $socialAccountLinks): JsonResponse
    {
        $customer = $request->customer();
        $attempt = $socialSignInAttempts->consume($provider, $request->state(), SocialSignInIntent::Link, $customer->id);
        $identity = $socialAccountLinks->connectToSignedIn($customer, $socialSignInAttempts->profile($attempt));

        return (new CustomerIdentityResource($identity))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Disconnect a provider.
     *
     * Needs your current password when your account has one. Refused when it
     * would leave you no way to sign in: set a password (through a password
     * reset) or connect another provider first.
     *
     * @throws LastSignInMethodException
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function destroy(DisconnectSocialIdentityRequest $request, string $identity, SocialAccountLinks $socialAccountLinks): Response
    {
        $customer = $request->customer();
        $socialAccountLinks->disconnect($customer, $customer->identities()->where('public_id', $identity)->firstOrFail(), $request->currentPassword());

        return response()->noContent();
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        return $customer;
    }
}
