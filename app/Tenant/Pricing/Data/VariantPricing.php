<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Tenant\Pricing\Models\FixedPrice;

/**
 * How one variant is priced beyond its base price: its tax class, its fixed prices, and what it costs in each currency the store offers.
 */
final readonly class VariantPricing
{
    /**
     * @param  string  $variantId  The variant's public ID.
     * @param  bool  $usesDefaultTaxClass  True when it was given no class of its own and follows the store's default.
     * @param  list<FixedPrice>  $fixedPrices  The prices the merchant set per currency, by currency code.
     * @param  list<VariantPrice>  $offeredPrices  What it costs now in each currency customers can buy in, the base currency first; empty while it is unpriced.
     */
    public function __construct(
        public string $variantId,
        public TaxClassData $taxClass,
        public bool $usesDefaultTaxClass,
        public array $fixedPrices = [],
        public array $offeredPrices = [],
    ) {}
}
