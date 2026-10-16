<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when shipping with a carrier before the shipment has a tracking number: the buyer needs it to follow their parcel.
 */
final class TrackingNumberRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'tracking_number_required';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['tracking_number' => [$this->translatedMessage()]];
    }
}
