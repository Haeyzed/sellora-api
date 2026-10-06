<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Actions\SignInPlatformAdmin;
use App\Shared\Auth\AccessTokenResource;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\Http\Requests\EmailSignInRequest;
use App\Shared\Http\Controller;

/**
 * Signing in to the platform admin app.
 */
final class SignInController extends Controller
{
    /**
     * Sign in as a platform admin.
     *
     * Returns a bearer token valid for 24 hours. Wrong details give the same
     * error whether or not the email has an account; after 5 wrong attempts for
     * an email, its sign-in pauses for 15 minutes.
     *
     * @unauthenticated
     *
     * @throws InvalidCredentialsException
     * @throws SignInTemporarilyLockedException
     * @throws AccountDeactivatedException
     */
    public function __invoke(EmailSignInRequest $request, SignInPlatformAdmin $signInPlatformAdmin): AccessTokenResource
    {
        $issuedAccessToken = $signInPlatformAdmin->handle(
            $request->normalisedEmail(),
            $request->string('password')->value(),
            $request->deviceName(),
        );

        return new AccessTokenResource($issuedAccessToken);
    }
}
