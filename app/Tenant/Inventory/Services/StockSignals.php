<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Services;

use App\Tenant\Inventory\Events\StockLevelChanged;
use App\Tenant\Inventory\Events\StockRunningLow;
use App\Tenant\Inventory\Events\VariantBackInStock;
use App\Tenant\Inventory\Events\VariantSoldOut;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * Announces what a stock change means, after it is saved: the level changed, and for counted variants whether they sold out, came back or are running low.
 *
 * A variant whose stock isn't counted never sells out or runs low. One that
 * allows backorders never sells out, because it can still be ordered.
 */
final readonly class StockSignals
{
    public function __construct(private FindStoreSettings $findStoreSettings) {}

    public function announce(StockItem $item, int $availableBefore): void
    {
        $availableAfter = $item->available();

        if ($availableAfter === $availableBefore) {
            return;
        }

        $variantId = $item->product_variant_id;
        $locationId = $item->stock_location_id;
        StockLevelChanged::dispatch($variantId, $locationId, $availableBefore, $availableAfter);

        $settings = InventoryItem::query()->where('product_variant_id', $variantId)->first();

        if ($settings !== null && ! $settings->tracks_stock) {
            return;
        }

        $allowsBackorder = $settings !== null && $settings->allows_backorder;
        $threshold = $settings->low_stock_threshold ?? $this->findStoreSettings->handle()->low_stock_threshold;

        if (! $allowsBackorder && $availableBefore > 0 && $availableAfter <= 0) {
            VariantSoldOut::dispatch($variantId, $locationId, $availableBefore, $availableAfter);
        }

        if ($availableBefore <= 0 && $availableAfter > 0) {
            VariantBackInStock::dispatch($variantId, $locationId, $availableBefore, $availableAfter);
        }

        if ($availableBefore > $threshold && $availableAfter <= $threshold && $availableAfter > 0) {
            StockRunningLow::dispatch($variantId, $locationId, $availableBefore, $availableAfter);
        }
    }
}
