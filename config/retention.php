<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Retention Periods (days)
    |--------------------------------------------------------------------------
    |
    | How long records that hold personal data, but are not core business
    | records, are kept before the daily purge deletes them. Each key is
    | used by one retention policy; a policy without a period here fails
    | loudly instead of keeping data forever.
    |
    */

    'periods' => [
        'idempotency_keys' => (int) env('RETENTION_IDEMPOTENCY_KEYS_DAYS', 1),
        'expired_tokens' => (int) env('RETENTION_EXPIRED_TOKENS_DAYS', 7),
        'expired_password_reset_tokens' => (int) env('RETENTION_EXPIRED_PASSWORD_RESET_TOKENS_DAYS', 1),
        'staff_invitations' => (int) env('RETENTION_STAFF_INVITATIONS_DAYS', 30),
        // Customers' verification and email-change links, after they expire.
        'customer_email_tokens' => (int) env('RETENTION_CUSTOMER_EMAIL_TOKENS_DAYS', 1),
        // Customers left in the trash are erased for good after this; restoring them before then keeps them.
        'trashed_customers' => (int) env('RETENTION_TRASHED_CUSTOMERS_DAYS', 90),
        // Customers' own data exports, after their download link expires.
        'customer_data_exports' => (int) env('RETENTION_CUSTOMER_DATA_EXPORTS_DAYS', 1),
        // Reservations that ended; the stock movements they caused are kept for good.
        'settled_stock_reservations' => (int) env('RETENTION_SETTLED_STOCK_RESERVATIONS_DAYS', 90),
        'released_promotion_uses' => (int) env('RETENTION_RELEASED_PROMOTION_USES_DAYS', 90),
        // Carts nobody has changed for this long; there are no abandoned-cart emails until Messaging (Step 20).
        'guest_carts' => (int) env('RETENTION_GUEST_CARTS_DAYS', 30),
        'customer_carts' => (int) env('RETENTION_CUSTOMER_CARTS_DAYS', 180),
        'store_registrations' => (int) env('RETENTION_STORE_REGISTRATIONS_DAYS', 7),
        'store_exports' => (int) env('RETENTION_STORE_EXPORTS_DAYS', 7),
        'activity_log' => (int) env('RETENTION_ACTIVITY_LOG_DAYS', 365),
        'audits' => (int) env('RETENTION_AUDITS_DAYS', 730),
        // Central market rates; orders keep the rate they used, so only recent history is needed.
        'exchange_rates' => (int) env('RETENTION_EXCHANGE_RATES_DAYS', 400),
        // Settled orders keep their buyer's personal data for the store's own period (its setting, or its
        // country's default in config/geography.php); this only sets a minimum, so 0 leaves it to the store.
        'settled_orders' => (int) env('RETENTION_SETTLED_ORDERS_MIN_DAYS', 0),
        // Invoices and credit notes keep their buyer's details for the store country's tax-record retention
        // (config/geography.php), and until their order is anonymised; this only sets a minimum, counted from issue.
        'tax_document_buyers' => (int) env('RETENTION_TAX_DOCUMENT_BUYERS_MIN_DAYS', 0),
        // What payment gateways sent in webhooks may name buyers; only the event ID is kept after this, so a resend is still recognised.
        'payment_webhook_payloads' => (int) env('RETENTION_PAYMENT_WEBHOOK_PAYLOADS_DAYS', 30),
        // Gateways stop resending an event long before this.
        'payment_webhook_events' => (int) env('RETENTION_PAYMENT_WEBHOOK_EVENTS_DAYS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Purges are bulk work, so they run on the bulk queue and never delay
    | urgent work such as order confirmations or payments.
    |
    */

    'queue' => env('RETENTION_QUEUE', 'bulk'),

];
