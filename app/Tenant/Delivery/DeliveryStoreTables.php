<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How drivers appear in a store export. Their PINs and setup codes never leave the store.
 */
final class DeliveryStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table(
            'drivers',
            include: ['id', 'public_id', 'name', 'phone', 'is_active', 'last_signed_in_at', 'erased_at', 'created_at', 'updated_at'],
            exclude: ['pin' => 'PIN hash, a secret.'],
        );

        $registry->table(
            'driver_setup_codes',
            include: ['id', 'driver_id', 'failed_attempts', 'expires_at', 'used_at', 'voided_at', 'issued_by_type', 'issued_by_id', 'created_at', 'updated_at'],
            exclude: ['code_hash' => 'Hash of a one-time PIN setup code, a secret.'],
        );
    }
}
