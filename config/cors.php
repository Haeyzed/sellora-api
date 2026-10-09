<?php

declare(strict_types=1);

$platform = require __DIR__.'/platform.php';
$platformDomain = (string) $platform['domain'];
$centralDomains = array_map(static fn (string $domain): string => preg_quote($domain, '#'), $platform['central_domains']);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Browsers may only call the API from known origins: the dashboard apps
    | listed in CORS_ALLOWED_ORIGINS, and storefronts served over HTTPS on a
    | store subdomain of the platform domain. Custom store domains are added
    | when custom domains are built. Webhooks are server-to-server and need
    | no CORS. Every client authenticates with bearer tokens, so credentials
    | (cookies) are never shared.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\.'.preg_quote($platformDomain, '#').'$#',
        // The API docs' "Try it", served on a central domain, calling a store's API. Only ever on a developer's
        // machine (API_DOCS_TRY_IT=false turns it off there too); never in production, whatever the variable says.
        ...(env('APP_ENV') === 'local' && env('API_DOCS_TRY_IT', true) ? ['#^https?://('.implode('|', $centralDomains).')(:\d+)?$#'] : []),
    ],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Idempotent-Replayed',
        'Retry-After',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 600,

    'supports_credentials' => false,

];
