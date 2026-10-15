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
        // A pickup's address is one of the store's own locations, not a person's.
        $registry->table('shipping_methods', include: ['id', 'public_id', 'shipping_zone_id', 'name', 'description', 'fulfilment', 'pricing', 'carrier', 'carrier_service', 'currency', 'free_over_amount', 'backup_amount', 'stock_location_id', 'pickup_country_code', 'pickup_state_code', 'pickup_address_line1', 'pickup_city', 'pickup_postal_code', 'pickup_instructions', 'min_days', 'max_days', 'position', 'is_active', 'archived_at', 'created_at', 'updated_at']);
        $registry->table('shipping_method_amounts', include: ['id', 'shipping_method_id', 'currency_code', 'free_over_amount', 'backup_amount']);
        $registry->table('shipping_rates', include: ['id', 'shipping_method_id', 'range_from', 'range_to', 'amount']);
        $registry->table('shipping_rate_amounts', include: ['id', 'shipping_rate_id', 'currency_code', 'price_amount']);
    }
}
