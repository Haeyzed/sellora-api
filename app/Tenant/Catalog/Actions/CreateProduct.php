<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Catalog\Data\ProductData;
use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Exceptions\ProductSlugTakenException;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\ProductCategories;
use App\Tenant\Catalog\Services\ProductLimit;
use App\Tenant\Catalog\Services\VariantDetails;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a product to the store's catalog as a draft, with its first variant priced in the store's base currency.
 *
 * Checks the plan's product limit first. Without a chosen slug, one is made
 * from the name in the default language.
 */
final readonly class CreateProduct
{
    public function __construct(
        private ProductLimit $productLimit,
        private ProductCategories $productCategories,
        private VariantDetails $variantDetails,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @throws UsageLimitReachedException When the plan has no room for another product.
     * @throws ProductSlugTakenException When the chosen slug is used by another product outside the trash.
     * @throws VariantSkuTakenException When the SKU is used by another variant outside the trash.
     */
    public function handle(ProductData $productData, ProductVariantData $variantData): Product
    {
        return Product::query()->getConnection()->transaction(function () use ($productData, $variantData): Product {
            $this->productLimit->ensureRoomForOneMore();

            $product = $this->createProduct($productData);
            $this->productCategories->replace($product, $productData->categories ?? [], $productData->primaryCategory);
            $this->createFirstVariant($product, $variantData);

            return $product;
        });
    }

    /**
     * @throws ProductSlugTakenException
     */
    private function createProduct(ProductData $productData): Product
    {
        $product = new Product;
        $product->changeTranslations('name', $productData->name ?? []);

        if ($productData->description !== null) {
            $product->changeTranslations('description', $productData->description);
        }

        if ($productData->slug !== null) {
            $product->slug = $productData->slug;
        }

        $product->brand_id = $productData->brand?->id;

        try {
            $product->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ProductSlugTakenException(previous: $exception);
        }

        return $product;
    }

    /**
     * @throws VariantSkuTakenException
     */
    private function createFirstVariant(Product $product, ProductVariantData $variantData): void
    {
        $variant = new ProductVariant;
        $variant->product_id = $product->id;
        $variant->position = 0;
        $this->variantDetails->apply($variant, $variantData, $this->findStoreSettings->handle()->currency_code);
        $this->variantDetails->save($variant, skuField: 'variant.sku');
    }
}
