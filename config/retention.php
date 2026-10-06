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
        'activity_log' => (int) env('RETENTION_ACTIVITY_LOG_DAYS', 365),
        'audits' => (int) env('RETENTION_AUDITS_DAYS', 730),
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
