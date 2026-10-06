<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\Actions\RegenerateRecoveryCodes;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\Exceptions\TwoFactorNotEnabledException;
use App\Shared\Auth\Http\Requests\CurrentPasswordRequest;
use App\Shared\Auth\Http\Resources\RecoveryCodesResource;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Http\Controller;

/**
 * Replacing the signed-in account's two-factor recovery codes.
 */
final class RecoveryCodeController extends Controller
{
    /**
     * Replace recovery codes.
     *
     * Needs the current password. Returns new recovery codes, shown only this
     * once; the old ones stop working.
     *
     * @throws TwoFactorNotEnabledException
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function __invoke(CurrentPasswordRequest $request, RegenerateRecoveryCodes $regenerateRecoveryCodes, SignedInAccount $signedInAccount): RecoveryCodesResource
    {
        return new RecoveryCodesResource($regenerateRecoveryCodes->handle(
            $signedInAccount->withTwoFactorFrom($request),
            $request->currentPassword(),
        ));
    }
}
