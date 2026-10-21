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
        'account_emails' => (int) env('RATE_LIMIT_ACCOUNT_EMAILS', 3),
        'registration' => (int) env('RATE_LIMIT_REGISTRATION', 3),
        'checkout' => (int) env('RATE_LIMIT_CHECKOUT', 10),
        // Codes entered per cart (or per signed-in customer), under a much higher ceiling per IP address.
        'coupon_redemption' => (int) env('RATE_LIMIT_COUPON_REDEMPTION', 10),
        'coupon_redemption_ip_ceiling' => (int) env('RATE_LIMIT_COUPON_REDEMPTION_IP_CEILING', 100),
        // Shoppers' carts: per cart (or per signed-in customer), under a much higher ceiling per IP address,
        // because many shoppers can share one address (section 14).
        'cart' => (int) env('RATE_LIMIT_CART', 60),
        'cart_ip_ceiling' => (int) env('RATE_LIMIT_CART_IP_CEILING', 600),
        'cart_creation_ip_ceiling' => (int) env('RATE_LIMIT_CART_CREATION_IP_CEILING', 100),
        'tenant_webhooks' => (int) env('RATE_LIMIT_TENANT_WEBHOOKS', 300),
        // Webhooks to endpoint IDs that lead nowhere, per IP address: guessing them is pointless, and this keeps it slow.
        'unknown_webhook_endpoints' => (int) env('RATE_LIMIT_UNKNOWN_WEBHOOK_ENDPOINTS', 30),
        'tenant_bulk_jobs' => (int) env('RATE_LIMIT_TENANT_BULK_JOBS', 60),
        // Invoice and credit note PDFs rendered per store per minute; the rest wait their turn on the queue.
        'tax_document_pdfs' => (int) env('RATE_LIMIT_TAX_DOCUMENT_PDFS', 30),
        // Customer social sign-ins started per IP address per store, high enough for many shoppers behind one address.
        'social_sign_in_ip_ceiling' => (int) env('RATE_LIMIT_SOCIAL_SIGN_IN_IP_CEILING', 60),
        // Answers from sign-in providers to the central relay page, per IP address.
        'social_sign_in_relay_ip_ceiling' => (int) env('RATE_LIMIT_SOCIAL_SIGN_IN_RELAY_IP_CEILING', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sign-in Lockout
    |--------------------------------------------------------------------------
    |
    | After this many wrong passwords or PINs for one email or phone in a
    | store, from any IP address, sign-in for it pauses. This stops slow
    | guessing spread over many addresses, which the per-minute "login" limit
    | alone can't, and matters most for short driver PINs.
    |
    */

    'sign_in_lockout' => [
        'max_failed_attempts' => (int) env('SIGN_IN_LOCKOUT_MAX_FAILED_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('SIGN_IN_LOCKOUT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor Authentication
    |--------------------------------------------------------------------------
    |
    | After a correct password, an account with two-factor authentication
    | gets a challenge instead of a token, valid for this many minutes, to
    | answer with a code from its authenticator app or a recovery code.
    | Wrong codes count towards the same lockout limits as sign-in.
    |
    */

    'two_factor' => [
        'challenge_minutes' => (int) env('TWO_FACTOR_CHALLENGE_MINUTES', 5),
        'recovery_code_count' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Breached Password Check
    |--------------------------------------------------------------------------
    |
    | New passwords are checked against known data breaches (Have I Been
    | Pwned); only the first 5 characters of the password's hash are sent.
    | If the service is slow or down, the check is skipped after this many
    | seconds rather than blocking sign-up or password changes.
    |
    */

    'breached_password_check' => [
        'timeout_in_seconds' => (int) env('BREACHED_PASSWORD_CHECK_TIMEOUT_SECONDS', 5),
    ],

];
