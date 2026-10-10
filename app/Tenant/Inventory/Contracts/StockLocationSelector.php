<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Contracts;

use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Models\StockLocation;

/**
 * Chooses the location stock of a variant is taken from, such as for a checkout.
 *
 * Core always answers with the store's default location. The MultiLocation
 * module binds its own selector (nearest warehouse, most stock and so on)
 * without core changing (section 6).
 */
interface StockLocationSelector
{
    public function locationFor(ProductVariant $variant): StockLocation;
}
