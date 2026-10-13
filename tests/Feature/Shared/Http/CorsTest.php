<?php

declare(strict_types=1);

beforeEach(function (): void {
    config(['cors.allowed_origins' => ['https://dashboard.example.com']]);
});

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
