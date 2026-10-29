<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How checkouts appear in a store export: all of them, contact details and addresses included, as for customers and orders; and what payment methods held for orders.
 */
final class CheckoutStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('checkouts', include: [
            'id', 'public_id', 'cart_id', 'customer_id', 'status', 'contact_email', 'contact_name', 'contact_phone', 'shipping_address', 'billing_address',
            'shipping_method_id', 'payment_method', 'module_payment_methods', 'buyer_tax_id', 'buyer_tax_id_country', 'marketing_email_consent',
            'marketing_sms_consent', 'customer_note', 'locale', 'quote_version', 'quote_hash', 'quote_expires_at', 'cart_version', 'exchange_rate',
            'order_id', 'placed_at', 'last_activity_at', 'created_at', 'updated_at',
        ]);
        $registry->table('checkout_payment_holds', include: [
            'id', 'public_id', 'order_id', 'method', 'reference', 'currency', 'amount_amount', 'status', 'committed_at', 'released_at', 'refunded_at',
            'refund_failed_at', 'refund_failure', 'settle_failed_at', 'settle_failure', 'settle_failures', 'settle_alerted_at', 'settle_recovered_at',
            'created_at', 'updated_at',
        ]);
    }
}
