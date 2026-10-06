<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Every list endpoint is paginated. Clients may ask for a page size up to
    | the maximum; anything larger is rejected rather than silently capped.
    |
    */

    'pagination' => [
        'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 25),
        'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | Requests carrying an Idempotency-Key are processed once. A repeat of
    | the same request within the replay window returns the first response.
    | The lease stops two copies of a request running at the same time.
    |
    */

    'idempotency' => [
        'lease_seconds' => (int) env('IDEMPOTENCY_LEASE_SECONDS', 60),
        'replay_window_hours' => (int) env('IDEMPOTENCY_REPLAY_WINDOW_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Every limiter key includes the store, so one store's traffic can never
    | use up another store's limit.
    |
    */

    'rate_limits' => [
        'api' => (int) env('RATE_LIMIT_API', 120),
        'public' => (int) env('RATE_LIMIT_PUBLIC', 60),
        'login' => (int) env('RATE_LIMIT_LOGIN', 5),
        'password_reset' => (int) env('RATE_LIMIT_PASSWORD_RESET', 3),
        'one_time_password' => (int) env('RATE_LIMIT_ONE_TIME_PASSWORD', 5),
        'registration' => (int) env('RATE_LIMIT_REGISTRATION', 3),
        'checkout' => (int) env('RATE_LIMIT_CHECKOUT', 10),
        'coupon_redemption' => (int) env('RATE_LIMIT_COUPON_REDEMPTION', 10),
        'tenant_webhooks' => (int) env('RATE_LIMIT_TENANT_WEBHOOKS', 300),
        'tenant_bulk_jobs' => (int) env('RATE_LIMIT_TENANT_BULK_JOBS', 60),
    ],

];
