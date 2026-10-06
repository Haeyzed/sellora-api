<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when no database server in the chosen hosting region is accepting new stores.
 */
final class HostingRegionUnavailableException extends DomainException
{
    public function errorCode(): string
    {
        return 'hosting_region_unavailable';
    }

    public function fieldErrors(): array
    {
        return ['hosting_region' => [$this->translatedMessage()]];
    }
}
