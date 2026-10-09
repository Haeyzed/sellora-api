<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Exceptions\ProductHasNoOptionsException;
use App\Tenant\Catalog\Exceptions\ProductOptionsIncompleteException;
use App\Tenant\Catalog\Exceptions\VariantCombinationTakenException;
use App\Tenant\Catalog\Exceptions\VariantLimitReachedException;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\VariantCombinations;
use App\Tenant\Catalog\Services\VariantDetails;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * Adds a variant to a product with options, such as "Blue, XL", with one value for each option, after the product's other variants.
 *
 * A product has at most as many variants outside the trash as
 * config/catalog.php allows, counted under a lock on the product so two
 * requests can't both take the last place. Amounts are in the store's base
 * currency, never one named by the request.
 */
final readonly class AddProductVariant
{
    public function __construct(
        private VariantCombinations $variantCombinations,
        private VariantDetails $variantDetails,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @param  list<AttributeValue>  $values  One for each of the product's options.
     *
     * @throws ProductHasNoOptionsException When the product has no options, so it has its one variant already.
     * @throws ProductOptionsIncompleteException When the values don't give exactly one value for each option.
     * @throws VariantLimitReachedException When the product already has as many variants as allowed.
     * @throws VariantCombinationTakenException When another variant outside the trash has the same values.
     * @throws VariantSkuTakenException When another variant outside the trash has the SKU.
     */
    public function handle(Product $product, array $values, ProductVariantData $details): ProductVariant
    {
        return Product::query()->getConnection()->transaction(function () use ($product, $values, $details): ProductVariant {
            Product::query()->whereKey($product->id)->lockForUpdate()->first();

            $optionAttributeIds = $this->variantCombinations->optionAttributeIdsOf($product);

            if ($optionAttributeIds === []) {
                throw new ProductHasNoOptionsException;
            }

            $this->variantCombinations->ensureComplete($values, $optionAttributeIds);

            $limit = config()->integer('catalog.max_variants_per_product');
            $variantsOutsideTheTrash = ProductVariant::query()->where('product_id', $product->id)->count();

            if ($variantsOutsideTheTrash >= $limit) {
                throw new VariantLimitReachedException($limit);
            }

            $variant = new ProductVariant;
            $variant->product_id = $product->id;
            $variant->position = (int) ProductVariant::query()->withTrashed()->where('product_id', $product->id)->max('position') + 1;
            $variant->attribute_signature = $this->variantCombinations->signatureOf($values);
            $this->variantDetails->apply($variant, $details, $this->findStoreSettings->handle()->currency_code);
            $this->variantDetails->save($variant);
            $this->variantCombinations->syncValues($variant, $values);

            return $variant;
        });
    }
}
