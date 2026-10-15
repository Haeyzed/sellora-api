<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Live carrier rates
    |--------------------------------------------------------------------------
    |
    | How long a carrier may take to quote before its methods fall back to
    | their backup price (or aren't offered), and how long a quote is reused
    | for the same parcels, addresses and currency. Failures are never kept.
    |
    */

    'carrier_timeout_seconds' => (int) env('SHIPPING_CARRIER_TIMEOUT_SECONDS', 5),

    'carrier_quote_cache_minutes' => (int) env('SHIPPING_CARRIER_QUOTE_CACHE_MINUTES', 10),

];
