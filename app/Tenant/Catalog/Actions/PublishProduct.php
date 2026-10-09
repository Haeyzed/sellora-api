<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Events\ProductPublished;
use App\Tenant\Catalog\Exceptions\ProductCannotBePublishedException;
use App\Tenant\Catalog\Exceptions\ProductStatusConflictException;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Makes a draft or archived product visible to customers.
 *
 * It needs a name in the store's default language and at least one priced
 * variant outside the trash; customers never see or buy an unpriced variant
 * (section 3.3). ProductPublished is announced once the change is saved.
 */
final readonly class PublishProduct
{
    /**
     * @throws ProductStatusConflictException When the product is already published.
     * @throws ProductCannotBePublishedException When it has no default-language name or no priced variant.
     */
    public function handle(Product $product): Product
    {
        return Product::query()->getConnection()->transaction(static function () use ($product): Product {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($product->status === ProductStatus::Active) {
                throw new ProductStatusConflictException;
            }

            $missing = [];

            if (trim((string) $product->getTranslation('name', $product->getFallbackLocale(), useFallbackLocale: false)) === '') {
                $missing[] = 'default_name';
            }

            if (! ProductVariant::query()->where('product_id', $product->id)->whereNotNull('price_amount')->exists()) {
                $missing[] = 'priced_variant';
            }

            if ($missing !== []) {
                throw new ProductCannotBePublishedException($missing);
            }

            $product->status = ProductStatus::Active;
            $product->save();

            ProductPublished::dispatch($product);

            return $product;
        });
    }
}
