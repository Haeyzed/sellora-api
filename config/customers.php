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

];
