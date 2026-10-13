<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Money;

/**
 * What one unit of a variant costs in one currency, and how that price came about (section 3.3).
 *
 * Either the merchant's fixed price for the currency, used as it is, or the
 * base price converted at the store's rate and rounded half-up to the
 * currency's decimals per unit. Read through the store's tax mode: it
 * includes tax in a tax-inclusive store.
 */
final readonly class VariantPrice
{
    /**
     * @param  int  $variantId  Its database ID.
     * @param  Money|null  $compareAtPrice  The "was" price, always higher than the price; null when there is none or conversion left it no higher.
     * @param  bool  $isFixed  True when the merchant set this price for the currency.
     * @param  bool  $includesTax  True in a tax-inclusive store.
     */
    public function __construct(
        public int $variantId,
        public Money $unitPrice,
        public ?Money $compareAtPrice,
        public bool $isFixed,
        public bool $includesTax,
        public AppliedExchangeRate $exchangeRate,
    ) {}
}
