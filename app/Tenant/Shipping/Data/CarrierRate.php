<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

use App\Shared\Money\Money;

/**
 * One of a carrier's live rates: a service and what it costs.
 */
final readonly class CarrierRate
{
    /**
     * @param  string  $service  One of the carrier's service codes.
     */
    public function __construct(
        public string $service,
        public Money $amount,
    ) {}
}
