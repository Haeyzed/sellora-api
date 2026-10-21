<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\FinishSocialSignIn;
use App\Tenant\Customers\Actions\StartSocialSignIn;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInStatus;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Exceptions\SocialProviderNotOfferedException;
use App\Tenant\Customers\Exceptions\SocialSignInFailedException;
use App\Tenant\Customers\Http\Requests\FinishSocialSignInRequest;
use App\Tenant\Customers\Http\Requests\StartSocialSignInRequest;
use App\Tenant\Customers\Http\Resources\SocialAuthorizationResource;
use App\Tenant\Customers\Http\Resources\SocialProviderResource;
use App\Tenant\Customers\Http\Resources\SocialSignInResultResource;
use App\Tenant\Customers\Services\SocialProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Customers signing in with a provider the store offers, such as Google or Apple (Step 10b). Customers only.
 *
 * The storefront sends the browser to the address returned. The provider
 * sends it back through the platform's relay page to this store's own
 * storefront, at its sign-in callback page, with the code and state in the
 * URL fragment; the storefront then finishes the sign-in here.
 */
final class SocialSignInController extends Controller
{
    /**
     * List sign-in providers.
     *
     * The providers the store offers.
     *
     * @unauthenticated
     */
    public function index(SocialProviders $socialProviders): AnonymousResourceCollection
    {
        return SocialProviderResource::collection($socialProviders->offered());
    }

    /**
     * Start signing in with a provider.
     *
     * Returns the provider's address to send the browser to, good for one
     * sign-in, for 10 minutes.
     *
     * @unauthenticated
     *
     * @throws SocialProviderNotOfferedException
     */
    public function store(StartSocialSignInRequest $request, SocialProvider $provider, StartSocialSignIn $startSocialSignIn): JsonResponse
    {
        return (new SocialAuthorizationResource($startSocialSignIn->handle($provider, null, $request->returnPath())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Finish signing in with a provider.
     *
     * Send the state and code the storefront's callback page read from its
     * URL fragment. Answers 200 with a bearer token once signed in (a new
     * account when the provider's user is new to the store). Answers 202
     * when the customer still has something to do, with a ticket for it:
     * "link_account" when an account already has the provider's email (it is
     * never connected automatically: prove it is yours first), or
     * "email_required" when the provider gave no email. A state works once,
     * in the store that started it. A shopper with a guest cart keeps it:
     * call POST /cart/merges with the token and the X-Cart-Token.
     *
     * @unauthenticated
     *
     * @throws InvalidSocialSignInException
     * @throws SocialSignInFailedException
     * @throws AccountDeactivatedException
     */
    public function callback(FinishSocialSignInRequest $request, SocialProvider $provider, FinishSocialSignIn $finishSocialSignIn): JsonResponse
    {
        $result = $finishSocialSignIn->handle($provider, $request->state(), $request->deviceName());

        if ($result->status !== SocialSignInStatus::SignedIn) {
            return (new SocialSignInResultResource($result))->response()->setStatusCode(Response::HTTP_ACCEPTED);
        }

        return (new SocialSignInResultResource($result))->response()->setStatusCode(Response::HTTP_OK);
    }
}
