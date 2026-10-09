<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Events\ProductArchived;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Moves a product and its variants to the trash: customers stop seeing it, its slug and SKUs are free for others, and it can be restored.
 *
 * Its variants go to the trash with it, marked as such, so restoring the
 * product later brings back exactly those and not variants trashed on their
 * own before. The product's own audit records the move.
 * Old orders still find it (FindProductVariant).
 */
final readonly class TrashProduct
{
    public function handle(Product $product): void
    {
        Product::query()->getConnection()->transaction(static function () use ($product): void {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $wasPublished = $product->status === ProductStatus::Active;

            $product->delete();
            ProductVariant::query()->where('product_id', $product->id)->update(['deleted_at' => $product->deleted_at, 'trashed_with_product' => true]);

            if ($wasPublished) {
                ProductArchived::dispatch($product);
            }
        });
    }
}
