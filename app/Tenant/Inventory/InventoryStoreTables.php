<?php

declare(strict_types=1);

namespace App\Tenant\Inventory;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How stock appears in a store export: all of it, including the full movement history. It holds no secrets; causers are account types and public IDs.
 */
final class InventoryStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('stock_locations', include: ['id', 'public_id', 'name', 'is_default', 'is_active', 'created_at', 'updated_at']);

        $registry->table(
            'inventory_items',
            include: ['id', 'product_variant_id', 'tracks_stock', 'allows_backorder', 'low_stock_threshold', 'created_at', 'updated_at'],
        );

        $registry->table(
            'stock_items',
            include: ['id', 'product_variant_id', 'stock_location_id', 'on_hand', 'reserved', 'created_at', 'updated_at'],
        );

        $registry->table(
            'stock_movements',
            include: [
                'id', 'public_id', 'stock_item_id', 'product_variant_id', 'stock_location_id', 'type', 'reason', 'note',
                'on_hand_delta', 'reserved_delta', 'on_hand_after', 'reserved_after', 'causer_type', 'causer_id',
                'source_type', 'source_id', 'created_at',
            ],
        );
    }
}
