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
