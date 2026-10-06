<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Auth\AccessTokenResource;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Http\Controller;
use App\Tenant\Delivery\Actions\SignInDriver;
use App\Tenant\Delivery\Http\Requests\DriverSignInRequest;

/**
 * Signing in to the driver app.
 */
final class SignInController extends Controller
{
    /**
     * Sign in as a driver.
     *
     * Returns a bearer token valid for 24 hours, usable only on this store. Wrong
     * details give the same error whether or not the phone number belongs to a
     * driver; after 5 wrong attempts for a phone number, its sign-in pauses for
     * 15 minutes. Forgotten PINs are reset by the store's staff.
     *
     * @unauthenticated
     *
     * @throws InvalidCredentialsException
     * @throws SignInTemporarilyLockedException
     * @throws AccountDeactivatedException
     */
    public function __invoke(DriverSignInRequest $request, SignInDriver $signInDriver): AccessTokenResource
    {
        $issuedAccessToken = $signInDriver->handle(
            $request->e164Phone(),
            $request->string('pin')->value(),
            $request->deviceName(),
        );

        return new AccessTokenResource($issuedAccessToken);
    }
}
