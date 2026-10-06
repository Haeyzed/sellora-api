<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a sign-up's code has expired. A new code can be requested.
 */
final class StoreRegistrationExpiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_registration_expired';
    }
}
