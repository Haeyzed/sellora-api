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
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * A zone's ID, made through the API with the regions given, such as ['NG', 'GH-AA'].
 *
 * @param  list<string>  $regions
 */
function shippingZone(string $name, array $regions = [], bool $restOfWorld = false): string
{
    $payload = ['name' => $name, 'covers_rest_of_world' => $restOfWorld, 'regions' => array_map(static function (string $region): array {
        [$country, $state] = [...explode('-', $region, 2), null];

        return ['country_code' => $country, 'state_code' => $state];
    }, $regions)];

    $id = shippingRequest('POST', '/zones', $payload)->assertCreated()->json('data.id');

    return is_string($id) ? $id : throw new LogicException('No zone came back.');
}
