<?php

declare(strict_types=1);

namespace App\Tenant\Inventory;

use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Contracts\StockLocationSelector;
use App\Tenant\Inventory\Models\StockLocation;

/**
 * Core's choice of location: always the store's one default location.
 */
final readonly class DefaultStockLocationSelector implements StockLocationSelector
{
    public function locationFor(ProductVariant $variant): StockLocation
    {
        return StockLocation::query()->where('is_default', true)->sole();
    }
}
