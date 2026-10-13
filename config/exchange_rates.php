<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Market Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Rates are fetched hourly for the whole platform (section 3.3). When no
    | fetch has succeeded for alert_after_hours, every run reports an error
    | for platform admins. A presentment currency whose market rate is older
    | than stale_after_hours, with no manual rate, stops being offered, so no
    | price is ever quoted on an old rate.
    |
    */

    'alert_after_hours' => (int) env('EXCHANGE_RATES_ALERT_AFTER_HOURS', 12),

    'stale_after_hours' => (int) env('EXCHANGE_RATES_STALE_AFTER_HOURS', 48),

];
