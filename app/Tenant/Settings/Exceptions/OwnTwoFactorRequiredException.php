<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when the owner tries to require two-factor authentication for staff without using it themselves.
 */
final class OwnTwoFactorRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'own_two_factor_required';
    }
}
