<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Http\Requests\SocialSignInRelayRequest;
use App\Landlord\Tenancy\Services\SocialSignInRelay;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * The relay page every social sign-in provider sends the browser back to (Step 10b), on the central domain: one platform-owned app per provider, many stores.
 *
 * It sends the browser on to the storefront of the store that started the
 * sign-in, found by the one-time state alone, with the code and state in the
 * URL fragment. It never redirects to an address from the request. A state
 * that is unknown, expired, already used or for another provider gets a
 * plain error page instead. Nothing is cached, and no referrer is sent on.
 *
 * @hideFromAPIDocumentation
 */
final class SocialSignInRelayController extends Controller
{
    public function __invoke(SocialSignInRelayRequest $request, string $provider, SocialSignInRelay $relay, ViewFactory $views): Response
    {
        $storeDomain = $relay->claim($provider, $request->state());

        if ($storeDomain === null) {
            $response = response($views->make('social-sign-in.relay-error', ['providerError' => $request->providerError()])->render(), Response::HTTP_BAD_REQUEST);
        } else {
            $response = redirect()->away($relay->storefrontUrl($storeDomain, $request->fragment()), Response::HTTP_SEE_OTHER);
        }

        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
