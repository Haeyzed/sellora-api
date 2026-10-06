<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Usage limits
    |--------------------------------------------------------------------------
    |
    | Every limit a plan can set. Each plan stores a value per key (null means
    | unlimited); a plan that leaves a key out allows none, so a forgotten
    | limit never gives a store unlimited use. Modules add their own keys here
    | when they are built.
    |
    */

    'limit_keys' => [
        'products',
        'staff_accounts',
        'locations',
        'custom_domains',
        'storage_in_megabytes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unpaid subscriptions
    |--------------------------------------------------------------------------
    |
    | How many days a store keeps full access after a subscription payment
    | fails, before it is suspended.
    |
    */

    'past_due_grace_period_in_days' => (int) env('FEATURES_PAST_DUE_GRACE_PERIOD_IN_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Each store's plan is cached so feature checks need no database query.
    | Plan and subscription changes clear the cache immediately; this is only
    | the upper bound.
    |
    */

    'cache_ttl_in_seconds' => 600,

];
