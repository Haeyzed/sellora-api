<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\Actions\ChangePassword;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Http\Requests\ChangePasswordRequest;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Http\Controller;
use Illuminate\Http\Response;

/**
 * Changing the password of the signed-in account.
 */
final class PasswordController extends Controller
{
    /**
     * Change password.
     *
     * Needs the current password. Every other device is signed out; the device
     * making this request stays signed in.
     *
     * @throws IncorrectCurrentPasswordException
     */
    public function update(ChangePasswordRequest $request, ChangePassword $changePassword, SignedInAccount $signedInAccount): Response
    {
        $changePassword->handle(
            $signedInAccount->from($request),
            $request->string('current_password')->value(),
            $request->string('password')->value(),
        );

        return response()->noContent();
    }
}
