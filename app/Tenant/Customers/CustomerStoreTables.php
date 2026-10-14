<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How customers appear in a store export. Their passwords, reset tokens and emailed links never leave the store.
 */
final class CustomerStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table(
            'customers',
            include: ['id', 'public_id', 'name', 'email', 'phone', 'locale', 'is_tax_exempt', 'tax_id', 'tax_id_country', 'default_shipping_address_id', 'default_billing_address_id', 'email_verified_at', 'is_active', 'last_signed_in_at', 'created_at', 'updated_at', 'deleted_at', 'erased_at'],
            exclude: ['password' => 'Password hash, a secret.', 'search_text' => 'A lower-case copy of the name, email and phone, which are exported.'],
        );

        $registry->table(
            'marketing_consents',
            include: ['id', 'public_id', 'customer_id', 'channel', 'contact', 'status', 'source', 'wording', 'locale', 'recorded_by_staff_id', 'created_at'],
        );

        $registry->table(
            'customer_notes',
            include: ['id', 'public_id', 'customer_id', 'author_staff_id', 'body', 'created_at'],
        );

        $registry->table(
            'customer_addresses',
            include: ['id', 'public_id', 'customer_id', 'label', 'recipient_name', 'company', 'line1', 'line2', 'city', 'state_code', 'postal_code', 'country_code', 'phone', 'created_at', 'updated_at'],
        );

        $registry->excludeTable('customer_password_reset_tokens', 'Password reset tokens are secrets.');
        $registry->table(
            'customer_data_exports',
            include: ['id', 'public_id', 'customer_id', 'status', 'created_at', 'completed_at', 'expires_at'],
            exclude: ['disk' => 'Where the file is stored, internal.', 'path' => 'Where the file is stored, internal; the files are the customers\' own downloads.'],
        );

        $registry->excludeTable('customer_email_tokens', 'Pending verification and email-change links: secrets that expire within a day.');
    }
}
