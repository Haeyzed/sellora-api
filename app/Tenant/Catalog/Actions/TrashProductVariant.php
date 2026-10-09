<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\ProductNeedsAVariantException;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Moves one variant to the trash: customers can't buy it any more, its SKU and combination are free, and it can be restored. Old orders still find it.
 *
 * The product keeps at least one variant outside the trash, counted under a
 * lock on the product.
 */
final readonly class TrashProductVariant
{
    /**
     * @throws ProductNeedsAVariantException When it is the product's last variant outside the trash.
     */
    public function handle(ProductVariant $variant): void
    {
        Product::query()->getConnection()->transaction(static function () use ($variant): void {
            Product::query()->whereKey($variant->product_id)->lockForUpdate()->first();

            if (ProductVariant::query()->where('product_id', $variant->product_id)->count() <= 1) {
                throw new ProductNeedsAVariantException;
            }

            $variant->delete();
        });
    }
}
