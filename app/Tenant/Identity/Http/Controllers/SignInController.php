<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Controllers;

use App\Shared\Auth\AccessTokenResource;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\Http\Requests\EmailSignInRequest;
use App\Shared\Auth\Http\Resources\TwoFactorChallengeResource;
use App\Shared\Auth\TwoFactor\PendingTwoFactorChallenge;
use App\Shared\Http\Controller;
use App\Tenant\Identity\Actions\SignInStaffMember;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signing in to a store's dashboard.
 */
final class SignInController extends Controller
{
    /**
     * Sign in as a staff member.
     *
     * Returns a bearer token valid for 24 hours, usable only on this store.
     * If the staff member uses two-factor authentication, it returns 202 with a
     * challenge instead: send its token and a code to the two-factor challenge
     * endpoint within 5 minutes. Wrong details give the same error whether or
     * not the email has an account; after 5 wrong attempts for an email, its
     * sign-in pauses for 15 minutes.
     *
     * @unauthenticated
     *
     * @throws InvalidCredentialsException
     * @throws SignInTemporarilyLockedException
     * @throws AccountDeactivatedException
     */
    public function __invoke(EmailSignInRequest $request, SignInStaffMember $signInStaffMember): AccessTokenResource|JsonResponse
    {
        $result = $signInStaffMember->handle(
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
