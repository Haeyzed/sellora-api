<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Exceptions\ProductRestoreConflictException;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\ProductLimit;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Brings a product back from the trash, with the variants that went to the trash with it, as a draft to check before publishing again.
 *
 * Re-checks the plan's product limit, and that nobody has taken its slug or
 * one of its variants' SKUs or combinations in the meantime; if anything
 * conflicts, nothing is restored. Restoring a product that isn't in the trash
 * changes nothing.
 */
final readonly class RestoreProduct
{
    public function __construct(private ProductLimit $productLimit) {}

    /**
     * @throws UsageLimitReachedException When the plan has no room for another product.
     * @throws ProductRestoreConflictException When its slug or a variant's SKU is now used outside the trash.
     */
    public function handle(Product $product): Product
    {
        return Product::query()->getConnection()->transaction(function () use ($product): Product {
            $product = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $product->trashed()) {
                return $product;
            }

            $this->productLimit->ensureRoomForOneMore();

            try {
                $product->restore();

                ProductVariant::onlyTrashed()
                    ->where('product_id', $product->id)
                    ->where('trashed_with_product', true)
                    ->get()
                    ->each(static function (ProductVariant $variant): void {
                        $variant->trashed_with_product = false;
                        $variant->restore();
                    });
            } catch (UniqueConstraintViolationException $exception) {
                throw new ProductRestoreConflictException(previous: $exception);
            }

            $product->status = ProductStatus::Draft;
            $product->save();

            return $product;
        });
    }
}
