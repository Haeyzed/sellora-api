<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\AccountReference;
use App\Shared\Auth\Models\Role;
use App\Tenant\Delivery\Actions\AssignDriver;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Models\Shipment;
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

/*
|--------------------------------------------------------------------------
| For DeliveriesTest.php and DriverDeliveriesTest.php
|--------------------------------------------------------------------------
*/

/**
 * An active driver of the current store, with a PIN.
 */
function activeDriver(string $phone = '+2348012345678', string $name = 'Dayo Driver'): Driver
{
    return Driver::factory()->create(['name' => $name, 'phone' => $phone, 'pin' => '482915', 'is_active' => true]);
}

/**
 * A shipment of an own-delivery order (2 of one variant, 5 in stock), waiting to leave, made in the current store by the given staff member.
 */
function pendingOwnDelivery(StaffMember $staff, bool $payOnDelivery = false): Shipment
{
    $order = ownDeliveryOrder($payOnDelivery);

    return app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 2], AccountReference::to($staff));
}

/**
 * A store for the delivery tests, in Nigeria, with room for drivers, its owner, and an active driver with a token.
 *
 * @return array{store: Tenant, owner: StaffMember, driver: Driver, token: string}
 */
function deliveryStore(string $subdomain = 'first-store'): array
{
    $store = createStore($subdomain, ['country_code' => 'NG']);
    allowDrivers($store->getTenantKey(), 10);
    [$owner, $driver] = $store->run(static function (): array {
        $owner = StaffMember::factory()->create(['password' => DELIVERY_STAFF_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return [$owner, activeDriver()];
    });

    return ['store' => $store, 'owner' => $owner, 'driver' => $driver, 'token' => driverTokenFor($driver, $store)];
}

/**
 * A delivery of a new own-delivery shipment, assigned to the driver by the staff member, in the given store.
 */
function assignedDelivery(Tenant $store, StaffMember $staff, Driver $driver, bool $payOnDelivery = false): Delivery
{
    return $store->run(static fn (): Delivery => app(AssignDriver::class)->handle($staff, pendingOwnDelivery($staff, $payOnDelivery), $driver));
}
