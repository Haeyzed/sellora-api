<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

use App\Shared\Money\Money;
use App\Tenant\Shipping\Models\ShippingMethod;

/**
 * One way a basket can be shipped, and what it costs in the basket's currency.
 */
final readonly class ShippingOption
{
    /**
     * @param  Money|null  $amount  What it costs; null when it is calculated at checkout (a live carrier rate, which needs a full address).
     * @param  bool  $isExact  True when the price is an exact amount set for the currency, false when converted from the base currency.
     * @param  bool  $isFree  True when the lines that ship reached its "free over" threshold.
     */
    public function __construct(
        public ShippingMethod $method,
        public ?Money $amount,
        public bool $isExact,
        public bool $isFree = false,
    ) {}

    public function isCalculatedAtCheckout(): bool
    {
        return $this->amount === null;
    }
}
