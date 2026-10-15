<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when two of a method's brackets cover the same weight or order value. Gaps are fine; overlaps never are.
 */
final class ShippingRatesOverlapException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipping_rates_overlap';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['rates' => [$this->translatedMessage()]];
    }
}
