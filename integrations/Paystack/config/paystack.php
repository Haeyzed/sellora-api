<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Paystack
    |--------------------------------------------------------------------------
    |
    | Stores connect their own Paystack accounts with their own keys, kept
    | encrypted in each store's database: no Paystack key belongs here or in
    | the environment. These only say where Paystack is and how long to wait
    | for it; a call that takes longer has an unknown outcome, never a failed one.
    |
    */

    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),

    'timeout_in_seconds' => (int) env('PAYSTACK_TIMEOUT_SECONDS', 15),

    'connect_timeout_in_seconds' => (int) env('PAYSTACK_CONNECT_TIMEOUT_SECONDS', 5),

];
