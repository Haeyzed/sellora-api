<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\StartSocialSignIn;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInIntent;
use App\Tenant\Customers\Exceptions\ErasureConfirmedByPasswordException;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Exceptions\SocialIdentityNotConnectedException;
use App\Tenant\Customers\Exceptions\SocialProviderNotOfferedException;
use App\Tenant\Customers\Exceptions\SocialSignInFailedException;
use App\Tenant\Customers\Http\Requests\LinkSocialIdentityRequest;
use App\Tenant\Customers\Http\Requests\StartSocialSignInRequest;
use App\Tenant\Customers\Http\Resources\ErasureConfirmationResource;
use App\Tenant\Customers\Http\Resources\SocialAuthorizationResource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\ErasureConfirmations;
use App\Tenant\Customers\Services\SocialSignInAttempts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogicException;

/**
 * How the signed-in customer without a password confirms erasing their account (section 3.3): an emailed link, or signing in again with a connected provider.
 */
final class CustomerErasureConfirmationController extends Controller
{
    /**
     * Email me an erasure link.
     *
     * For accounts without a password. Sends a link to your account's email;
     * its token confirms "erase my account" while you are signed in, once,
     * within 15 minutes. A new link replaces the last.
     *
     * @throws ErasureConfirmedByPasswordException
     */
    public function sendEmail(Request $request, ErasureConfirmations $erasureConfirmations): Response
    {
        $erasureConfirmations->sendEmail($this->customer($request));

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    /**
     * Start signing in again to erase.
     *
     * For accounts without a password. Returns the provider's address to send
     * the browser to; finish with "sign in again to erase" once it comes back
     * to the storefront's callback.
     *
     * @throws SocialProviderNotOfferedException
     * @throws ErasureConfirmedByPasswordException
     */
    public function start(StartSocialSignInRequest $request, SocialProvider $provider, StartSocialSignIn $startSocialSignIn): JsonResponse
    {
        $customer = $this->customer($request);

        if ($customer->password !== null) {
            throw new ErasureConfirmedByPasswordException;
        }

        return (new SocialAuthorizationResource($startSocialSignIn->handle($provider, SocialSignInIntent::Reauthenticate, $customer, $request->returnPath())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Sign in again to erase.
     *
     * With the state and code from the storefront's callback, for a sign-in
     * you started to erase. The provider account must be one connected to
     * yours. Returns a token that confirms "erase my account" once, within
     * 5 minutes.
     *
     * @throws InvalidSocialSignInException
     * @throws SocialSignInFailedException
     * @throws SocialIdentityNotConnectedException
     * @throws ErasureConfirmedByPasswordException
     */
    public function store(LinkSocialIdentityRequest $request, SocialProvider $provider, SocialSignInAttempts $socialSignInAttempts, ErasureConfirmations $erasureConfirmations): JsonResponse
    {
        $customer = $request->customer();
        $attempt = $socialSignInAttempts->consume($provider, $request->state(), SocialSignInIntent::Reauthenticate, $customer->id);
        $confirmation = $erasureConfirmations->afterReauthentication($customer, $socialSignInAttempts->profile($attempt));

        return (new ErasureConfirmationResource($confirmation))->response()->setStatusCode(Response::HTTP_CREATED);
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
