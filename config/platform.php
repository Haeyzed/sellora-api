<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Domain
    |--------------------------------------------------------------------------
    |
    | The domain under which every store gets its subdomain, for example
    | "mystore.<domain>". The final platform domain is not decided yet, so it
    | always comes from the environment and is never hard-coded.
    |
    */

    'domain' => env('PLATFORM_DOMAIN', 'sellora.test'),

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | Hosts that serve the landlord API and webhooks. Requests to any other
    | host are treated as tenant requests and identified by their domain.
    |
    */

    'central_domains' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('PLATFORM_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Hosting Regions
    |--------------------------------------------------------------------------
    |
    | Where a store's database, files and backups can live. Merchants choose
    | one at registration, and only regions with a database server accepting
    | new stores are offered. Names are in lang/<locale>/regions.php.
    |
    */

    'regions' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('PLATFORM_REGIONS', 'africa,eu,us')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Store Registration
    |--------------------------------------------------------------------------
    |
    | "plan" is the code of the plan every new store starts on, until merchant
    | billing is decided. Registration stays closed while it doesn't exist.
    |
    */

    // The link in the "your store is ready" email; {domain} becomes the store's platform domain.
    'store_dashboard_url' => env('STORE_DASHBOARD_URL', 'https://{domain}/admin'),

    'store_registration' => [
        'plan' => env('STORE_REGISTRATION_PLAN', 'free'),
        'verification_code_expire_minutes' => (int) env('STORE_REGISTRATION_CODE_EXPIRE_MINUTES', 60),
        'max_stores_per_email' => (int) env('STORE_REGISTRATION_MAX_STORES_PER_EMAIL', 3),
        'reserved_subdomains' => [
            'www', 'api', 'app', 'admin', 'administrator', 'mail', 'email', 'smtp', 'imap', 'pop', 'ftp', 'ns1', 'ns2',
            'static', 'assets', 'cdn', 'media', 'files', 'docs', 'help', 'support', 'status', 'blog', 'news',
            'billing', 'payments', 'checkout', 'account', 'accounts', 'login', 'signin', 'signup', 'register',
            'dashboard', 'platform', 'store', 'stores', 'shop', 'root', 'system', 'security', 'staging', 'dev',
            'test', 'demo', 'sellora',
        ],
    ],

];
