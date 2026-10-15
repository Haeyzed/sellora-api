<?php

declare(strict_types=1);

namespace App\Tenant\Shipping;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How the store's shipping setup appears in a store export: all of it. It is store configuration, with no personal data and no secrets.
 */
final class ShippingStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('shipping_zones', include: ['id', 'public_id', 'name', 'is_active', 'covers_rest_of_world', 'created_at', 'updated_at']);
        $registry->table('shipping_zone_regions', include: ['id', 'shipping_zone_id', 'country_code', 'state_code']);
    }
}
