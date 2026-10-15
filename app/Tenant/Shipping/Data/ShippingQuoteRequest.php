<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

use App\Tenant\Pricing\Data\AppliedExchangeRate;

/**
 * What QuoteShipping prices: a basket's lines, in a currency at the store's rate, to a destination.
 */
final readonly class ShippingQuoteRequest
{
    /**
     * @param  AppliedExchangeRate  $exchangeRate  The rate the basket is priced at, from the base currency to its currency.
     * @param  list<ShippableLine>  $lines  Every line; those that don't ship are left out of weight and value.
     * @param  ShippingRegion  $destination  Where it would go: a country, and its state when known.
     */
    public function __construct(
        public AppliedExchangeRate $exchangeRate,
        public array $lines,
        public ShippingRegion $destination,
    ) {}

    public function currency(): string
    {
        return $this->exchangeRate->currency;
    }
}
