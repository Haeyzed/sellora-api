<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Pricing\Data\AppliedExchangeRate;

/**
 * The currency a cart is priced in now: the one the shopper chose while the store offers it, otherwise the store's own (Step 9's fallback).
 */
final readonly class CartCurrency
{
    /**
     * @param  string|null  $unavailable  The currency the shopper chose, when the store doesn't offer it right now (for example while its rate is out of date).
     */
    public function __construct(
        public AppliedExchangeRate $rate,
        public ?string $unavailable,
    ) {}

    public function code(): string
    {
        return $this->rate->currency;
    }
}
