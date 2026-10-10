<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Models\InventoryItem;

/**
 * Changes how a variant's stock is handled: whether it is counted, whether it may be sold when none is left, and its own low-stock threshold. Audited.
 *
 * Turning backorders off keeps reservations already made beyond the stock;
 * it only refuses new ones (section 13).
 */
final readonly class ChangeInventorySettings
{
    public function handle(ProductVariant $variant, InventorySettingsData $changes): InventoryItem
    {
        $item = InventoryItem::query()->where('product_variant_id', $variant->id)->first();

        if ($item === null) {
            $item = new InventoryItem;
            $item->product_variant_id = $variant->id;
        }

        if ($changes->tracksStock !== null) {
            $item->tracks_stock = $changes->tracksStock;
        }

        if ($changes->allowsBackorder !== null) {
            $item->allows_backorder = $changes->allowsBackorder;
        }

        if ($changes->usesStoreThreshold) {
            $item->low_stock_threshold = null;
        } elseif ($changes->lowStockThreshold !== null) {
            $item->low_stock_threshold = $changes->lowStockThreshold;
        }

        $item->save();

        return $item;
    }
}
