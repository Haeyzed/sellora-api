<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Services;

use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Actions\FindVariantTaxClasses;
use App\Tenant\Pricing\Data\VariantPricing;
use App\Tenant\Pricing\Models\VariantTaxClass;

/**
 * Puts together one variant's pricing for its screen.
 */
final readonly class VariantPricingDetails
{
    public function __construct(private FindVariantTaxClasses $findVariantTaxClasses) {}

    public function of(ProductVariant $variant): VariantPricing
    {
        return new VariantPricing(
            variantId: $variant->public_id,
            taxClass: $this->findVariantTaxClasses->handle([$variant->id])[$variant->id],
            usesDefaultTaxClass: ! VariantTaxClass::query()->where('product_variant_id', $variant->id)->exists(),
        );
    }
}
