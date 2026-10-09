<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Tenant\Catalog\Jobs\RefreshBrandProductsSearchText;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Keeps each product's search text current: its name in every language, its variants' SKUs and barcodes, and its brand's name in every language, in lower case.
 *
 * Searches match any part of it, case-insensitively, through a trigram index
 * (pg_trgm), so "linen", "LIN-0" or a brand name all find the product, in any
 * of the store's languages. One product is rebuilt at once whenever it, one
 * of its variants or its brand changes; a brand renamed with many products
 * is rebuilt on the bulk queue instead, so the request stays fast.
 */
final readonly class ProductSearchText
{
    /** A brand with more products than this is rebuilt on the bulk queue. */
    private const int REBUILD_AT_ONCE_UP_TO = 50;

    public function refresh(int $productId): void
    {
        $product = Product::withTrashed()->with('brand')->find($productId);

        if ($product === null) {
            return;
        }

        $codes = ProductVariant::query()->where('product_id', $productId)->get(['sku', 'barcode'])
            ->flatMap(static fn (ProductVariant $variant): array => [$variant->sku, $variant->barcode])
            ->all();
        $parts = [
            ...array_values($product->getTranslations('name')),
            ...$codes,
            ...array_values($product->brand?->getTranslations('name') ?? []),
        ];
        $words = array_unique(array_filter(array_map(static fn (?string $part): string => mb_strtolower(trim((string) $part)), $parts)));

        Product::withTrashed()->whereKey($productId)->toBase()->update(['search_text' => implode(' ', $words)]);
    }

    /**
     * Rebuilds every product of a renamed brand: at once for a few, on the bulk queue for many.
     */
    public function refreshBrand(Brand $brand): void
    {
        $productIds = Product::withTrashed()->where('brand_id', $brand->id)->limit(self::REBUILD_AT_ONCE_UP_TO + 1)->pluck('id');

        if ($productIds->count() > self::REBUILD_AT_ONCE_UP_TO) {
            RefreshBrandProductsSearchText::dispatch($brand->id);

            return;
        }

        $productIds->each(fn (int $productId) => $this->refresh($productId));
    }
}
