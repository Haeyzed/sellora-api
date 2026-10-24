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

    /*
    |--------------------------------------------------------------------------
    | Manual test-mode run (paystack:manual-test)
    |--------------------------------------------------------------------------
    |
    | The one exception to "no Paystack key in the environment", and only on a
    | developer's own machine: a Paystack TEST secret key (sk_test_…) in the
    | local .env, never committed, for running one real payment and refund in
    | Paystack's test mode by hand. The command refuses any other key, any
    | environment but local, and CI. Received webhooks it is asked to keep go
    | to a git-ignored folder.
    |
    */

    'manual_test' => [
        'secret_key' => env('PAYSTACK_MANUAL_TEST_SECRET_KEY'),
        'in_ci' => (bool) env('CI', false),
        'webhooks_path' => storage_path('app/private/paystack-webhooks'),
        'refund_checks' => (int) env('PAYSTACK_MANUAL_TEST_REFUND_CHECKS', 6),
        'refund_check_every_seconds' => (int) env('PAYSTACK_MANUAL_TEST_REFUND_CHECK_SECONDS', 10),
    ],

];
