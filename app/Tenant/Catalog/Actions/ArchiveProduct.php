<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Events\ProductArchived;
use App\Tenant\Catalog\Exceptions\ProductStatusConflictException;
use App\Tenant\Catalog\Models\Product;

/**
 * Hides a product from customers without deleting it; it can be published again. Old orders keep showing it.
 *
 * ProductArchived is announced once the change is saved, when the product
 * was visible to customers.
 */
final readonly class ArchiveProduct
{
    /**
     * @throws ProductStatusConflictException When the product is already archived.
     */
    public function handle(Product $product): Product
    {
        return Product::query()->getConnection()->transaction(static function () use ($product): Product {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($product->status === ProductStatus::Archived) {
                throw new ProductStatusConflictException;
            }

            $wasPublished = $product->status === ProductStatus::Active;
            $product->status = ProductStatus::Archived;
            $product->save();

            if ($wasPublished) {
                ProductArchived::dispatch($product);
            }

            return $product;
        });
    }
}
