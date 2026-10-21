<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when finishing a social sign-in this store didn't start, or one already finished, expired, or started for another provider or purpose. Start again.
 */
final class InvalidSocialSignInException extends DomainException
{
    public function errorCode(): string
    {
        return 'social_sign_in_invalid';
    }
}
