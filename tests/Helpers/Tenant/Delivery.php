<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For DriverAuthTest.php
|--------------------------------------------------------------------------
*/

function signInAsDriver(string $phone, string $pin): TestResponse
{
    $response = test()->postJson(storeUrl('first-store', '/api/v1/driver/auth/tokens'), ['phone' => $phone, 'pin' => $pin]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For DriverManagementTest.php and DriverPinTest.php
|--------------------------------------------------------------------------
*/

const DELIVERY_STAFF_PASSWORD = 'a-long-delivery-password';

function allowDrivers(string $tenantId, int $limit): void
{
    test()->featureSource->give($tenantId, FakeFeatureSource::snapshot(limits: ['drivers' => $limit]));
    app(App\Shared\Features\Features::class)->forget($tenantId);
}

/**
 * A staff member of the given store holding a role with exactly these permissions.
 *
 * @param  list<string>  $permissions
 */
function deliveryStaffWith(Tenant $store, array $permissions): StaffMember
{
    return $store->run(static function () use ($permissions): StaffMember {
        $role = Role::findOrCreate('Delivery '.implode(' ', $permissions ?: ['none']), StaffMember::GUARD);
        $role->syncPermissions(array_map(static fn (string $permission) => Permission::findOrCreate($permission, StaffMember::GUARD), $permissions));
        $staffMember = StaffMember::factory()->create(['password' => DELIVERY_STAFF_PASSWORD]);
        $staffMember->assignRole($role);

        return $staffMember;
    });
}

/**
 * Sends a request to a store's staff delivery API as the given staff member, or with no token.
 */
function deliveryRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'first-store'): TestResponse
{
    $request = $as === null ? test()->withoutToken() : test()->withToken(staffTokenFor($as, $subdomain));

    forgetSignIns();
    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/delivery/'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Sends a request to the driver app's API with a bearer token, or with none.
 */
function driverAppRequest(string $method, string $path, array $data = [], ?string $token = null, string $subdomain = 'first-store'): TestResponse
{
    $request = $token === null ? test()->withoutToken() : test()->withToken($token);

    forgetSignIns();
    $response = $request->json($method, storeUrl($subdomain, '/api/v1/driver/'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function driverTokenFor(Driver $driver, Tenant $store): string
{
    return $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($driver, Driver::GUARD, 'test')->plainTextToken);
}

/**
 * Adds a driver through the staff API and returns the driver's public ID and the setup code shown once.
 *
 * @return array{id: string, code: string}
 */
function addDriverWithCode(StaffMember $as, string $phone = '+2348012345678', string $name = 'Dayo Driver'): array
{
    $response = deliveryRequest('POST', 'drivers', ['name' => $name, 'phone' => $phone], $as)->assertCreated();

    return ['id' => (string) $response->json('data.driver.id'), 'code' => (string) $response->json('data.setup_code')];
}

function chooseDriverPin(string $phone, string $code, string $pin, string $subdomain = 'first-store'): TestResponse
{
    return driverAppRequest('POST', 'auth/pin', ['phone' => $phone, 'setup_code' => $code, 'pin' => $pin], subdomain: $subdomain);
}

/**
 * Whether a driver token still works on the driver's own profile.
 */
function driverTokenWorks(string $token): bool
{
    return driverAppRequest('GET', 'auth/me', token: $token)->status() === 200;
}
