<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Services;

use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Actions\FindVariantTaxClasses;
use App\Tenant\Pricing\Data\VariantPricing;
use App\Tenant\Pricing\Models\FixedPrice;
use App\Tenant\Pricing\Models\VariantTaxClass;
use App\Tenant\Pricing\VariantPricer;

/**
 * Puts together one variant's pricing for its screen.
 */
final readonly class VariantPricingDetails
{
    public function __construct(
        private FindVariantTaxClasses $findVariantTaxClasses,
        private FindExchangeRates $findExchangeRates,
        private VariantPricer $variantPricer,
    ) {}

    public function of(ProductVariant $variant): VariantPricing
    {
        $offeredPrices = [];

        foreach ($this->findExchangeRates->offered() as $rate) {
            $price = $this->variantPricer->priceAt([$variant], $rate)[$variant->id] ?? null;

            if ($price !== null) {
                $offeredPrices[] = $price;
            }
        }

        return new VariantPricing(
            variantId: $variant->public_id,
            taxClass: $this->findVariantTaxClasses->handle([$variant->id])[$variant->id],
            usesDefaultTaxClass: ! VariantTaxClass::query()->where('product_variant_id', $variant->id)->exists(),
            fixedPrices: array_values(FixedPrice::query()->where('product_variant_id', $variant->id)->orderBy('currency_code')->get()->all()),
            offeredPrices: $offeredPrices,
        );
    }
}
