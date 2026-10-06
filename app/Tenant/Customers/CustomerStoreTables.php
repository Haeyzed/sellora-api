<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How customers appear in a store export. Their passwords and reset tokens never leave the store.
 */
final class CustomerStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table(
            'customers',
            include: ['id', 'public_id', 'name', 'email', 'is_active', 'last_signed_in_at', 'created_at', 'updated_at', 'deleted_at'],
            exclude: ['password' => 'Password hash, a secret.'],
        );

        $registry->excludeTable('customer_password_reset_tokens', 'Password reset tokens are secrets.');
    }
}
