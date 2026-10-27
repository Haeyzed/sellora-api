<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a PIN setup code is wrong, used, expired or voided, or the phone number has no code. The same error for all, so it reveals nothing.
 */
final class InvalidDriverSetupCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'invalid_driver_setup_code';
    }
}
