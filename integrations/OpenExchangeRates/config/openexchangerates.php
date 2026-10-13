<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Open Exchange Rates
    |--------------------------------------------------------------------------
    |
    | The platform's market exchange rate provider. The free plan (hourly
    | updates, USD base) is enough for development. The app ID is a secret:
    | set it in the environment only.
    |
    */

    'app_id' => env('OPEN_EXCHANGE_RATES_APP_ID'),

    'base_url' => env('OPEN_EXCHANGE_RATES_BASE_URL', 'https://openexchangerates.org/api/'),

    'timeout_in_seconds' => (int) env('OPEN_EXCHANGE_RATES_TIMEOUT_SECONDS', 10),

];
