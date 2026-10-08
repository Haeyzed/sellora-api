<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\VariantDetails;

/**
 * Changes a variant's price, compare-at price, SKU, barcode or shipping details.
 *
 * Amounts stay in the variant's currency, which is the store's base
 * currency: that can't change once anything is priced.
 */
final readonly class UpdateProductVariant
{
    public function __construct(private VariantDetails $variantDetails) {}

    /**
     * @throws VariantSkuTakenException When the new SKU is used by another variant outside the trash.
     */
    public function handle(ProductVariant $variant, ProductVariantData $changes): ProductVariant
    {
        $this->variantDetails->apply($variant, $changes, $variant->currency);
        $this->variantDetails->save($variant);

        return $variant;
    }
}
