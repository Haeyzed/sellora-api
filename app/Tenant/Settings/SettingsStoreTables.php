<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How the store's settings appear in a store export: all of them, since none is a secret.
 */
final class SettingsStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('store_settings', include: [
            'id', 'name', 'country_code', 'currency_code', 'timezone', 'default_locale', 'enabled_locales', 'tax_mode',
            'weight_unit', 'dimension_unit', 'low_stock_threshold', 'contact_email', 'contact_phone', 'address_line1', 'address_line2', 'address_city',
            'address_state_code', 'address_postal_code', 'address_country_code', 'require_staff_two_factor', 'created_at', 'updated_at',
        ]);
    }
}
