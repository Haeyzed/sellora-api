<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when the chosen subdomain belongs to another store or is held by another sign-up in progress.
 */
final class SubdomainTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'subdomain_taken';
    }

    public function fieldErrors(): array
    {
        return ['subdomain' => [$this->translatedMessage()]];
    }
}
