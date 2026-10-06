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

];
