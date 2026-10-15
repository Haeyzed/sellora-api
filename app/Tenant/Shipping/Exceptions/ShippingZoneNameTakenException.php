<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another zone already has the name, in any case.
 */
final class ShippingZoneNameTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipping_zone_name_taken';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['name' => [$this->translatedMessage()]];
    }
}
