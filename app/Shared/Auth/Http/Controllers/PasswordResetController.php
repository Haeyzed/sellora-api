<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\Actions\ResetPassword;
use App\Shared\Auth\Exceptions\InvalidPasswordResetException;
use App\Shared\Auth\Http\Requests\ResetPasswordRequest;
use App\Shared\Http\Controller;
use Illuminate\Http\Response;

/**
 * Choosing a new password from a reset link. The route says which kind of account through its "broker" default.
 */
final class PasswordResetController extends Controller
{
    /**
     * Reset password.
     *
     * Sets the new password using the token from the emailed link, then signs the
     * account out on every device. Sign in again with the new password.
     *
     * @throws InvalidPasswordResetException
     */
    public function store(ResetPasswordRequest $request, ResetPassword $resetPassword): Response
    {
        $resetPassword->handle(
            (string) $request->route('broker'),
            $request->normalisedEmail(),
            $request->string('token')->value(),
            $request->string('password')->value(),
        );

        return response()->noContent();
    }
}
