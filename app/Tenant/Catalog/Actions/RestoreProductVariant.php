<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\ProductOptionsIncompleteException;
use App\Tenant\Catalog\Exceptions\ProductStatusConflictException;
use App\Tenant\Catalog\Exceptions\VariantLimitReachedException;
use App\Tenant\Catalog\Exceptions\VariantRestoreConflictException;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\VariantCombinations;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Brings a variant back from the trash, as long as it still fits its product.
 *
 * Re-checks under a lock on the product: its values must still give one
 * value for each of the product's options (which may have changed), the
 * product must have room for another variant, and no variant outside the
 * trash may have taken its SKU or combination. Restoring a variant that isn't in
 * the trash changes nothing.
 */
final readonly class RestoreProductVariant
{
    public function __construct(private VariantCombinations $variantCombinations) {}

    /**
     * @throws ProductStatusConflictException When the product itself is in the trash; restore the product instead.
     * @throws VariantRestoreConflictException When its values no longer match the options, or its SKU or combination is taken.
     * @throws VariantLimitReachedException When the product already has as many variants as allowed.
     */
    public function handle(ProductVariant $variant): ProductVariant
    {
        if (! $variant->trashed()) {
            return $variant;
        }

        return Product::query()->getConnection()->transaction(function () use ($variant): ProductVariant {
            $product = Product::withTrashed()->whereKey($variant->product_id)->lockForUpdate()->firstOrFail();

            if ($product->trashed()) {
                throw new ProductStatusConflictException;
            }

            try {
                $this->variantCombinations->ensureComplete(array_values($variant->attributeValues()->get()->all()), $this->variantCombinations->optionAttributeIdsOf($product));
            } catch (ProductOptionsIncompleteException $exception) {
                throw new VariantRestoreConflictException(previous: $exception);
            }

            $limit = config()->integer('catalog.max_variants_per_product');

            if (ProductVariant::query()->where('product_id', $product->id)->count() >= $limit) {
                throw new VariantLimitReachedException($limit);
            }

            try {
                $variant->restore();
            } catch (UniqueConstraintViolationException $exception) {
                throw new VariantRestoreConflictException(previous: $exception);
            }

            return $variant;
        });
    }
}
