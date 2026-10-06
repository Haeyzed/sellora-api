<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Actions\SignInPlatformAdmin;
use App\Shared\Auth\AccessTokenResource;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\Http\Requests\EmailSignInRequest;
use App\Shared\Auth\Http\Resources\TwoFactorChallengeResource;
use App\Shared\Auth\TwoFactor\PendingTwoFactorChallenge;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signing in to the platform admin app.
 */
final class SignInController extends Controller
{
    /**
     * Sign in as a platform admin.
     *
     * Once two-factor authentication is set up, returns 202 with a challenge:
     * send its token and a code to the two-factor challenge endpoint within 5
     * minutes to get the access token. Before it is set up, returns a bearer
     * token (valid for 24 hours) that can only set it up. Wrong details give
     * the same error whether or not the email has an account; after 5 wrong
     * attempts for an email, its sign-in pauses for 15 minutes.
     *
     * @unauthenticated
     *
     * @throws InvalidCredentialsException
     * @throws SignInTemporarilyLockedException
     * @throws AccountDeactivatedException
     */
    public function __invoke(EmailSignInRequest $request, SignInPlatformAdmin $signInPlatformAdmin): AccessTokenResource|JsonResponse
    {
        $result = $signInPlatformAdmin->handle(
            $request->normalisedEmail(),
            $request->string('password')->value(),
            $request->deviceName(),
        );

        if ($result instanceof PendingTwoFactorChallenge) {
            return (new TwoFactorChallengeResource($result))->response()->setStatusCode(Response::HTTP_ACCEPTED);
        }

        return new AccessTokenResource($result);
    }
}
