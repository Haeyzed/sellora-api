<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How the store's tax setup appears in a store export: all of it. It holds no personal data and no secrets.
 */
final class PricingStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('tax_classes', include: ['id', 'public_id', 'name', 'is_exempt', 'is_default', 'used_for_shipping', 'created_at', 'updated_at']);
        $registry->table('tax_rates', include: ['id', 'public_id', 'tax_class_id', 'country_code', 'state_code', 'name', 'rate', 'created_at', 'updated_at']);
        $registry->table('variant_tax_classes', include: ['id', 'product_variant_id', 'tax_class_id', 'created_at', 'updated_at']);
    }
}
