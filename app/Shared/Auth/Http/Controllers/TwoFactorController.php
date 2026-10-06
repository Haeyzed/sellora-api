<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\Actions\DisableTwoFactor;
use App\Shared\Auth\Actions\StartTwoFactorSetup;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\Exceptions\TwoFactorAlreadyEnabledException;
use App\Shared\Auth\Exceptions\TwoFactorNotEnabledException;
use App\Shared\Auth\Http\Requests\CurrentPasswordRequest;
use App\Shared\Auth\Http\Requests\DisableTwoFactorRequest;
use App\Shared\Auth\Http\Resources\TwoFactorSetupResource;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Http\Controller;
use Illuminate\Http\Response;

/**
 * Turning two-factor authentication on and off for the signed-in account.
 */
final class TwoFactorController extends Controller
{
    /**
     * Start two-factor setup.
     *
     * Needs the current password. Returns a new secret to add to an
     * authenticator app; nothing changes at sign-in until the setup is
     * confirmed with a code from the app. Starting again replaces an
     * unconfirmed secret.
     *
     * @throws TwoFactorAlreadyEnabledException
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function store(CurrentPasswordRequest $request, StartTwoFactorSetup $startTwoFactorSetup, SignedInAccount $signedInAccount): TwoFactorSetupResource
    {
        return new TwoFactorSetupResource($startTwoFactorSetup->handle(
            $signedInAccount->withTwoFactorFrom($request),
            $request->currentPassword(),
        ));
    }

    /**
     * Turn two-factor authentication off.
     *
     * Needs the current password and a current code or a recovery code.
     * Removes the secret and recovery codes. Platform admins must then set it
     * up again before doing anything else.
     *
     * @throws TwoFactorNotEnabledException
     * @throws IncorrectCurrentPasswordException
     * @throws InvalidTwoFactorCodeException
     * @throws TooManyIncorrectAttemptsException
     */
    public function destroy(DisableTwoFactorRequest $request, DisableTwoFactor $disableTwoFactor, SignedInAccount $signedInAccount): Response
    {
        $disableTwoFactor->handle(
            $signedInAccount->withTwoFactorFrom($request),
            $request->currentPassword(),
            $request->code(),
            $request->recoveryCode(),
        );

        return response()->noContent();
    }
}
