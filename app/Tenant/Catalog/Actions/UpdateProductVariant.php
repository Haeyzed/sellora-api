<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\VariantDetails;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * Changes a variant's price, compare-at price, SKU, barcode or shipping details.
 *
 * Amounts are in the store's base currency, never one named by the request.
 * A variant priced before already has that currency, because the base
 * currency can't change once anything is priced.
 */
final readonly class UpdateProductVariant
{
    public function __construct(
        private VariantDetails $variantDetails,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @throws VariantSkuTakenException When the new SKU is used by another variant outside the trash.
     */
    public function handle(ProductVariant $variant, ProductVariantData $changes): ProductVariant
    {
        $this->variantDetails->apply($variant, $changes, $this->findStoreSettings->handle()->currency_code);
        $this->variantDetails->save($variant);

        return $variant;
    }
}
