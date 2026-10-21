<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when the provider didn't accept the sign-in's code, or didn't answer. Start again.
 */
final class SocialSignInFailedException extends DomainException
{
    public function errorCode(): string
    {
        return 'social_sign_in_failed';
    }
}
