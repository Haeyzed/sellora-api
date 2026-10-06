<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Actions\CompletePlatformAdminSignIn;
use App\Shared\Auth\AccessTokenResource;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorChallengeException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\Http\Requests\TwoFactorChallengeRequest;
use App\Shared\Http\Controller;

/**
 * The second step of signing in to the platform admin app.
 */
final class TwoFactorChallengeController extends Controller
{
    /**
     * Answer a two-factor challenge.
     *
     * Send the challenge token from sign-in with either the authenticator code
     * or a recovery code. Returns a bearer token valid for 24 hours. After 5
     * wrong codes, sign-in for the account pauses for 15 minutes.
     *
     * @unauthenticated
     *
     * @throws InvalidTwoFactorChallengeException
     * @throws InvalidTwoFactorCodeException
     * @throws SignInTemporarilyLockedException
     * @throws AccountDeactivatedException
     */
    public function __invoke(TwoFactorChallengeRequest $request, CompletePlatformAdminSignIn $completePlatformAdminSignIn): AccessTokenResource
    {
        return new AccessTokenResource($completePlatformAdminSignIn->handle(
            $request->challengeToken(),
            $request->code(),
            $request->recoveryCode(),
        ));
    }
}
