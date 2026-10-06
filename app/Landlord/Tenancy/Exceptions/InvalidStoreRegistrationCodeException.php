<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a verification code is wrong, already used, or has had too many wrong tries. The same error for each, so it reveals nothing.
 */
final class InvalidStoreRegistrationCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_registration_code_invalid';
    }

    public function fieldErrors(): array
    {
        return ['code' => [$this->translatedMessage()]];
    }
}
