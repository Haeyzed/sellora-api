<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Services;

use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Data\VariantStock;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockLocation;

/**
 * Puts together one variant's stock for its screen: settings (or the defaults) and its level at every active location.
 */
final readonly class VariantStocks
{
    public function __construct(private StockLevels $stockLevels) {}

    public function of(ProductVariant $variant): VariantStock
    {
        $item = InventoryItem::query()->where('product_variant_id', $variant->id)->first();
        $levels = [];

        foreach (StockLocation::query()->active()->orderByDesc('is_default')->orderBy('id')->get() as $location) {
            $row = $this->stockLevels->at($location->id, variantId: $variant->id)->first();

            if ($row !== null) {
                $levels[] = StockLevels::fromRow($row);
            }
        }

        return new VariantStock(
            variantId: $variant->public_id,
            tracksStock: $item->tracks_stock ?? true,
            allowsBackorder: $item->allows_backorder ?? false,
            ownLowStockThreshold: $item?->low_stock_threshold,
            levels: $levels,
        );
    }
}
