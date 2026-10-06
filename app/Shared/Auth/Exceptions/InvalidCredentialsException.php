<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when sign-in details are wrong. The same error is used whether or not the account exists, so it reveals nothing.
 */
final class InvalidCredentialsException extends DomainException
{
    public function errorCode(): string
    {
        return 'invalid_credentials';
    }
}
