<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\Actions\ConfirmTwoFactorSetup;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TwoFactorSetupNotStartedException;
use App\Shared\Auth\Http\Requests\TwoFactorCodeRequest;
use App\Shared\Auth\Http\Resources\RecoveryCodesResource;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Http\Controller;

/**
 * Confirming two-factor setup with a code from the authenticator app.
 */
final class TwoFactorConfirmationController extends Controller
{
    /**
     * Confirm two-factor setup.
     *
     * Turns two-factor authentication on and returns recovery codes, shown only
     * this once. Every other device is signed out; this one stays signed in.
     *
     * @throws TwoFactorSetupNotStartedException
     * @throws InvalidTwoFactorCodeException
     */
    public function __invoke(TwoFactorCodeRequest $request, ConfirmTwoFactorSetup $confirmTwoFactorSetup, SignedInAccount $signedInAccount): RecoveryCodesResource
    {
        return new RecoveryCodesResource($confirmTwoFactorSetup->handle(
            $signedInAccount->withTwoFactorFrom($request),
            $request->code(),
        ));
    }
}
