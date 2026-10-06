<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How drivers appear in a store export. Their PINs never leave the store.
 */
final class DeliveryStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table(
            'drivers',
            include: ['id', 'public_id', 'name', 'phone', 'is_active', 'last_signed_in_at', 'created_at', 'updated_at'],
            exclude: ['pin' => 'PIN hash, a secret.'],
        );
    }
}
