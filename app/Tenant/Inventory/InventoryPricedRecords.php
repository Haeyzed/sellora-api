<?php

declare(strict_types=1);

namespace App\Tenant\Inventory;

use App\Shared\Money\Contracts\PricedRecords;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockMovement;

/**
 * Tells Settings whether inventory holds a cost, which locks the store's base currency (section 3.3): every unit cost is in that currency.
 *
 * Costs recorded on past receipts count too, since they stay in the history.
 */
final readonly class InventoryPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return InventoryItem::query()->whereNotNull('unit_cost')->exists()
            || StockMovement::query()->whereNotNull('unit_cost')->exists();
    }
}
