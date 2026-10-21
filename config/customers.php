<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Customers (Tenant\Customers)
|--------------------------------------------------------------------------
|
| The emailed one-time links of a store's customer accounts, into the
| storefront. {token} is filled in and {domain} becomes the store domain the
| request came from, so each store's links point to itself. Only a link's
| hash is stored; sending a new link for the same purpose replaces the old.
|
*/

return [

    /* The most addresses one customer can save. */
    'max_addresses' => (int) env('CUSTOMER_MAX_ADDRESSES', 20),

    /*
     * A customer's export of their personal data: a JSON file on a private
     * disk (tenancy gives each store its own folder of "local"), downloadable
     * by the customer for this many hours, then deleted.
     */
    'data_exports' => [
        'disk' => env('CUSTOMER_DATA_EXPORT_DISK', 'local'),
        'expire_hours' => (int) env('CUSTOMER_DATA_EXPORT_EXPIRE_HOURS', 168),
    ],

    /* Proves the customer receives mail at their account's email. Sent at sign-up and on request. */
    'email_verification' => [
        'url' => env('CUSTOMER_EMAIL_VERIFICATION_URL', 'https://{domain}/verify-email?token={token}'),
        'expire_minutes' => (int) env('CUSTOMER_EMAIL_VERIFICATION_EXPIRE_MINUTES', 1440),
    ],

    /* Sent to the new address; the change happens only when the customer, signed in, uses it. */
    'email_change' => [
        'url' => env('CUSTOMER_EMAIL_CHANGE_URL', 'https://{domain}/account/confirm-email?token={token}'),
        'expire_minutes' => (int) env('CUSTOMER_EMAIL_CHANGE_EXPIRE_MINUTES', 60),
    ],

    /*
     * Sent to an existing account's email when someone signs in with a
     * trusted provider asserting that same email, so its owner can confirm
     * connecting the provider. Only for trusted providers (below).
     */
    'social_link' => [
        'url' => env('CUSTOMER_SOCIAL_LINK_URL', 'https://{domain}/account/confirm-sign-in-link?token={token}'),
        'expire_minutes' => (int) env('CUSTOMER_SOCIAL_LINK_EXPIRE_MINUTES', 30),
    ],

    /*
     * Confirms erasure for a customer without a password (section 3.3): a
     * link emailed to their account address, used while signed in, or a
     * token given after a fresh sign-in with a connected provider, which
     * must be used within minutes.
     */
    'erasure' => [
        'url' => env('CUSTOMER_ERASURE_URL', 'https://{domain}/account/confirm-erasure?token={token}'),
        'expire_minutes' => (int) env('CUSTOMER_ERASURE_EXPIRE_MINUTES', 15),
        'reauthentication_expire_minutes' => (int) env('CUSTOMER_ERASURE_REAUTHENTICATION_EXPIRE_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social sign-in (Step 10b, customers only)
    |--------------------------------------------------------------------------
    |
    | One platform-owned app per provider (credentials in config/services.php).
    | A provider is offered only when its credentials are set, and only by
    | stores that choose it. Every provider redirects to the relay page on
    | the central domain, which sends the answer on to the store bound to the
    | one-time state: its own domain plus the storefront path below, never an
    | address from the request.
    |
    */

    'social' => [
        'providers' => ['google', 'apple'],

        /*
         * Providers whose "this email is verified" the store believes: a new
         * account starts verified (and gets the guest orders placed with the
         * email), and an existing account's owner may prove ownership by an
         * emailed link. Any other provider's email starts unverified, and
         * only the account's password proves ownership.
         */
        'trusted_email_providers' => ['google', 'apple'],

        // https://{central domain}/oauth/{provider}/callback, registered with each provider.
        'relay_url' => env('SOCIAL_SIGN_IN_RELAY_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/oauth/{provider}/callback'),

        // Where the relay sends the browser back; {domain} becomes the store's own domain.
        'storefront_callback_url' => env('SOCIAL_SIGN_IN_STOREFRONT_CALLBACK_URL', 'https://{domain}/auth/social/callback'),

        // How long a started sign-in, and a ticket for what it still needs, stay usable.
        'state_expire_minutes' => (int) env('SOCIAL_SIGN_IN_STATE_EXPIRE_MINUTES', 10),
        'ticket_expire_minutes' => (int) env('SOCIAL_SIGN_IN_TICKET_EXPIRE_MINUTES', 10),
    ],

];
