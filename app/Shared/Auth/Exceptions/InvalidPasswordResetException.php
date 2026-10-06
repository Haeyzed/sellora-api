<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a password reset link is wrong, already used or expired. Says the same whether or not the email has an account.
 */
final class InvalidPasswordResetException extends DomainException
{
    public function errorCode(): string
    {
        return 'password_reset_invalid';
    }
}
