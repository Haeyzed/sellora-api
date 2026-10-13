<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

/**
 * How one variant is priced beyond its base price: its tax class.
 */
final readonly class VariantPricing
{
    /**
     * @param  string  $variantId  The variant's public ID.
     * @param  bool  $usesDefaultTaxClass  True when it was given no class of its own and follows the store's default.
     */
    public function __construct(
        public string $variantId,
        public TaxClassData $taxClass,
        public bool $usesDefaultTaxClass,
    ) {}
}
