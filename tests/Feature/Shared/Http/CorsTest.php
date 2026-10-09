<?php

declare(strict_types=1);

beforeEach(function (): void {
    config(['cors.allowed_origins' => ['https://dashboard.example.com']]);
});

/**
 * Sends a browser CORS preflight for a GET request from the given origin.
 */
function preflightFrom(string $origin): Illuminate\Testing\TestResponse
{
    return test()->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/products');
}

it('allows a configured dashboard origin', function (): void {
    preflightFrom('https://dashboard.example.com')
        ->assertHeader('Access-Control-Allow-Origin', 'https://dashboard.example.com');
});

it('allows a storefront on a store subdomain over HTTPS', function (): void {
    $storefront = 'https://mystore.'.config('platform.domain');

    preflightFrom($storefront)->assertHeader('Access-Control-Allow-Origin', $storefront);
});

it('rejects a store subdomain served over plain HTTP', function (): void {
    preflightFrom('http://mystore.'.config('platform.domain'))
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('rejects an unknown origin', function (): void {
    preflightFrom('https://evil.example.net')->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('rejects a lookalike domain that only ends with the platform domain', function (): void {
    preflightFrom('https://mystore.'.config('platform.domain').'.evil.net')
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

/**
 * Loads config/cors.php as it would be with these environment variables.
 *
 * @param  array<string, string|null>  $environment  Null unsets the variable.
 * @return array<string, mixed>
 */
function corsConfigWith(array $environment): array
{
    $previous = [];

    foreach ($environment as $name => $value) {
        $previous[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        } else {
            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    try {
        return require base_path('config/cors.php');
    } finally {
        foreach ($previous as $name => [$server, $env, $process]) {
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }

            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }

            putenv($process === false ? $name : "{$name}={$process}");
        }
    }
}

it('lets the API docs on a central domain try the store API on a developer\'s machine only', function (): void {
    $docsOrigin = 'http://'.config()->array('platform.central_domains')[0];

    config(['cors' => corsConfigWith(['APP_ENV' => 'local', 'API_DOCS_TRY_IT' => null])]);
    preflightFrom($docsOrigin)->assertHeader('Access-Control-Allow-Origin', $docsOrigin);
    preflightFrom($docsOrigin.'.evil.net')->assertHeaderMissing('Access-Control-Allow-Origin');

    config(['cors' => corsConfigWith(['APP_ENV' => 'production', 'API_DOCS_TRY_IT' => null])]);
    preflightFrom($docsOrigin)->assertHeaderMissing('Access-Control-Allow-Origin');

    config(['cors' => corsConfigWith(['APP_ENV' => 'production', 'API_DOCS_TRY_IT' => 'true'])]);
    preflightFrom($docsOrigin)->assertHeaderMissing('Access-Control-Allow-Origin');

    config(['cors' => corsConfigWith(['APP_ENV' => 'staging', 'API_DOCS_TRY_IT' => 'true'])]);
    preflightFrom($docsOrigin)->assertHeaderMissing('Access-Control-Allow-Origin');

    config(['cors' => corsConfigWith(['APP_ENV' => 'local', 'API_DOCS_TRY_IT' => 'false'])]);
    preflightFrom($docsOrigin)->assertHeaderMissing('Access-Control-Allow-Origin');
});
