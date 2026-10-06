<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when an email already owns, or is signing up for, as many stores as one person may have.
 */
final class StoresPerEmailLimitReachedException extends DomainException
{
    public function errorCode(): string
    {
        return 'stores_per_email_limit_reached';
    }

    public function fieldErrors(): array
    {
        return ['email' => [$this->translatedMessage()]];
    }
}
