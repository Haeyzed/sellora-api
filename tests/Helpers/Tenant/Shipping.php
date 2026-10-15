<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For the tests in tests/Feature/Tenant/Shipping
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to a store's staff shipping API, under /api/v1/staff/shipping, as the test's owner unless another staff member is given.
 *
 * @param  array<string, mixed>  $data
 */
function shippingRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'shipping-store'): TestResponse
{
    forgetSignIns();
    $store = test()->store;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/shipping'.$path), $data);
    test()->flushHeaders();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * A zone's ID, made through the API with the regions given, such as ['NG', 'GH-AA'].
 *
 * @param  list<string>  $regions
 */
function shippingZone(string $name, array $regions = [], bool $restOfWorld = false, string $subdomain = 'shipping-store'): string
{
    $payload = ['name' => $name, 'covers_rest_of_world' => $restOfWorld, 'regions' => array_map(static function (string $region): array {
        [$country, $state] = [...explode('-', $region, 2), null];

        return ['country_code' => $country, 'state_code' => $state];
    }, $regions)];

    $id = shippingRequest('POST', '/zones', $payload, subdomain: $subdomain)->assertCreated()->json('data.id');

    return is_string($id) ? $id : throw new LogicException('No zone came back.');
}

/*
|--------------------------------------------------------------------------
| For ShippingQuotesTest.php
|--------------------------------------------------------------------------
*/

/**
 * A method's ID, made through the API.
 *
 * @param  array<string, mixed>  $fields
 */
function quotedMethod(array $fields): string
{
    $id = shippingRequest('POST', '/methods', ['fulfilment' => 'ship', 'pricing' => 'flat', 'name' => ['en' => 'Method'], ...$fields])->assertCreated()->json('data.id');

    return is_string($id) ? $id : throw new LogicException('No method came back.');
}

/**
 * The preview of shipping for lines of [variant ID, quantity].
 *
 * @param  list<array{string, int}>  $lines
 * @return array<string, mixed>
 */
function shippingQuoteFor(array $lines, string $country = 'NG', ?string $state = null, ?string $currency = null): array
{
    $data = array_filter(['country_code' => $country, 'state_code' => $state, 'currency' => $currency, 'lines' => array_map(static fn (array $line): array => ['variant' => $line[0], 'quantity' => $line[1]], $lines)], static fn (mixed $value): bool => $value !== null);

    return (array) shippingRequest('POST', '/quotes', $data)->assertOk()->json('data');
}
