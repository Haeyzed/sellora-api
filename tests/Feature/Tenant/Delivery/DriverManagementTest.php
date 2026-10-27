<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Delivery\Actions\CreateDriver;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Models\DriverSetupCode;
use App\Tenant\Delivery\Services\DriverLimit;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 10, Step 17: staff add, change, deactivate and erase the store's
 * drivers, but never set or know a PIN. They issue a one-time setup code
 * instead. Active drivers count towards the plan's limit, checked under a
 * lock. Nothing crosses from one store to another, and no other guard's
 * token works here.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);

    $this->store = createStore('first-store');
    allowDrivers($this->store->getTenantKey(), 10);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create(['password' => DELIVERY_STAFF_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('adds a driver without a PIN and shows a one-time setup code just once, keeping only its hash', function (): void {
    $response = deliveryRequest('POST', 'drivers', ['name' => 'Dayo Driver', 'phone' => '0801 234 5678'], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.driver.name', 'Dayo Driver')
        ->assertJsonPath('data.driver.phone', '+2348012345678')
        ->assertJsonPath('data.driver.has_pin', false)
        ->assertJsonPath('data.driver.is_active', true)
        ->assertJsonMissingPath('data.driver.pin');
    $code = (string) $response->json('data.setup_code');

    expect($code)->toMatch('/^\d{8}$/')
        ->and(CarbonImmutable::parse($response->json('data.expires_at'))->diffInHours(CarbonImmutable::now(), absolute: true))->toBeGreaterThan(71.9);

    [$storedHash, $activityText] = $this->store->run(static fn (): array => [
        DriverSetupCode::query()->sole()->code_hash,
        Activity::query()->where('log_name', 'delivery')->pluck('properties')->toJson().Activity::query()->where('log_name', 'delivery')->pluck('description')->implode(' '),
    ]);
    expect($storedHash)->not->toContain($code)
        ->and($activityText)->not->toContain($code)
        ->and($activityText)->not->toContain('Dayo');

    deliveryRequest('GET', "drivers/{$response->json('data.driver.id')}", as: $this->owner)
        ->assertOk()
        ->assertJsonMissingPath('data.setup_code');
    signInAsDriver('+2348012345678', '000000')->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
});

it('refuses a phone number another driver already signs in with, whatever its format', function (): void {
    addDriverWithCode($this->owner, '+2348012345678');

    deliveryRequest('POST', 'drivers', ['name' => 'Second', 'phone' => '0801-234-5678'], $this->owner)
        ->assertConflict()
        ->assertJsonPath('code', 'driver_phone_taken');
});

it('needs a name and a valid phone number', function (): void {
    deliveryRequest('POST', 'drivers', [], $this->owner)->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
    deliveryRequest('POST', 'drivers', ['name' => 'Dayo', 'phone' => '12'], $this->owner)->assertUnprocessable()->assertJsonValidationErrors(['phone']);
});

it('lets delivery.view see drivers, drivers.manage change them, and nobody else either', function (): void {
    $driver = addDriverWithCode($this->owner);
    $viewer = deliveryStaffWith($this->store, ['delivery.view']);
    $manager = deliveryStaffWith($this->store, ['drivers.manage']);
    $nobody = deliveryStaffWith($this->store, []);

    deliveryRequest('GET', 'drivers', as: $viewer)->assertOk()->assertJsonPath('meta.total', 1);
    deliveryRequest('GET', "drivers/{$driver['id']}", as: $viewer)->assertOk();
    deliveryRequest('POST', 'drivers', ['name' => 'X', 'phone' => '+2348012345679'], $viewer)->assertForbidden();
    deliveryRequest('POST', "drivers/{$driver['id']}/setup-codes", as: $viewer)->assertForbidden();
    deliveryRequest('POST', "drivers/{$driver['id']}/deactivate", as: $viewer)->assertForbidden();
    deliveryRequest('GET', "drivers/{$driver['id']}/personal-data", as: $viewer)->assertForbidden();

    deliveryRequest('POST', "drivers/{$driver['id']}/setup-codes", as: $manager)->assertCreated();
    deliveryRequest('GET', 'drivers', as: $nobody)->assertForbidden();
    deliveryRequest('GET', 'drivers')->assertUnauthorized();
});

it('pages drivers by number, sorted by name, and filters active or deactivated ones', function (): void {
    addDriverWithCode($this->owner, '+2348012345671', 'Zainab');
    $ada = addDriverWithCode($this->owner, '+2348012345672', 'Ada');
    deliveryRequest('POST', "drivers/{$ada['id']}/deactivate", as: $this->owner)->assertOk();

    deliveryRequest('GET', 'drivers', as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Ada', 'Zainab'])
        ->assertJsonPath('meta.last_page', 1);
    deliveryRequest('GET', 'drivers?active=1', as: $this->owner)->assertJsonPath('data.*.name', ['Zainab']);
    deliveryRequest('GET', 'drivers?active=0', as: $this->owner)->assertJsonPath('data.*.name', ['Ada']);
});

it('counts only active drivers towards the plan limit', function (): void {
    allowDrivers($this->store->getTenantKey(), 1);
    $first = addDriverWithCode($this->owner, '+2348012345671');

    deliveryRequest('POST', 'drivers', ['name' => 'Second', 'phone' => '+2348012345672'], $this->owner)
        ->assertForbidden()
        ->assertJsonPath('code', 'usage_limit_reached');

    deliveryRequest('POST', "drivers/{$first['id']}/deactivate", as: $this->owner)->assertOk();
    $second = addDriverWithCode($this->owner, '+2348012345672');

    deliveryRequest('POST', "drivers/{$first['id']}/reactivate", as: $this->owner)
        ->assertForbidden()
        ->assertJsonPath('code', 'usage_limit_reached');
    expect($second['id'])->not->toBe('');
});

it('allows no drivers on a plan without the limit', function (): void {
    $this->featureSource->give($this->store->getTenantKey(), FakeFeatureSource::snapshot());
    app(App\Shared\Features\Features::class)->forget($this->store->getTenantKey());

    deliveryRequest('POST', 'drivers', ['name' => 'Dayo', 'phone' => '+2348012345678'], $this->owner)
        ->assertForbidden()
        ->assertJsonPath('code', 'usage_limit_reached');
});

it('waits for a concurrent request adding a driver, so two can\'t both take the last place', function (): void {
    allowDrivers($this->store->getTenantKey(), 1);
    $owner = $this->owner;

    $this->store->run(static function () use ($owner): void {
        $createDriver = static fn () => app(CreateDriver::class)->handle($owner, 'Second', '+2348012345672');

        // The other request has checked the limit and added the store's last driver, but not yet committed.
        $otherRequest = SecondConnection::open()->holdAdvisoryLock(DriverLimit::LIMIT_KEY);
        $otherRequest->run(static fn () => Driver::factory()->connection(SecondConnection::NAME)->create(['phone' => '+2348012345671']));

        expect($otherRequest->blocks($createDriver))->toBeTrue();

        $otherRequest->commit();

        expect($createDriver)->toThrow(UsageLimitReachedException::class)
            ->and(Driver::query()->count())->toBe(1);
    });
});

it('clears the PIN, signs the driver out everywhere and voids the old code when staff issue a new code', function (): void {
    $driver = addDriverWithCode($this->owner);
    $token = chooseDriverPin('+2348012345678', $driver['code'], '482915')->assertOk()->json('data.token');
    $secondCode = deliveryRequest('POST', "drivers/{$driver['id']}/setup-codes", as: $this->owner)->assertCreated()->json('data.setup_code');
    $thirdCode = deliveryRequest('POST', "drivers/{$driver['id']}/setup-codes", as: $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.driver.has_pin', false)
        ->json('data.setup_code');

    expect(driverTokenWorks($token))->toBeFalse();
    signInAsDriver('+2348012345678', '482915')->assertUnprocessable();
    chooseDriverPin('+2348012345678', $secondCode, '593726')->assertUnprocessable()->assertJsonPath('code', 'invalid_driver_setup_code');
    chooseDriverPin('+2348012345678', $thirdCode, '593726')->assertOk();

    $issued = $this->store->run(static fn (): int => Activity::query()->where('event', 'driver_setup_code_issued')->count());
    expect($issued)->toBe(3);
});

it('treats a new phone number as a new way in: the PIN is cleared and the driver signed out', function (): void {
    $driver = addDriverWithCode($this->owner);
    $token = chooseDriverPin('+2348012345678', $driver['code'], '482915')->json('data.token');

    deliveryRequest('PATCH', "drivers/{$driver['id']}", ['name' => 'Dayo D.'], $this->owner)->assertOk()->assertJsonPath('data.has_pin', true);
    expect(driverTokenWorks($token))->toBeTrue();

    deliveryRequest('PUT', "drivers/{$driver['id']}", ['phone' => '+2348099999999'], $this->owner)
        ->assertOk()
        ->assertJsonPath('data.phone', '+2348099999999')
        ->assertJsonPath('data.has_pin', false);
    expect(driverTokenWorks($token))->toBeFalse();
    signInAsDriver('+2348099999999', '482915')->assertUnprocessable();
});

it('signs a deactivated driver out at once, voids their code and refuses them new codes until reactivated', function (): void {
    $driver = addDriverWithCode($this->owner);
    $token = chooseDriverPin('+2348012345678', $driver['code'], '482915')->json('data.token');
    $unusedCode = deliveryRequest('POST', "drivers/{$driver['id']}/setup-codes", as: $this->owner)->json('data.setup_code');

    deliveryRequest('POST', "drivers/{$driver['id']}/deactivate", as: $this->owner)->assertOk()->assertJsonPath('data.is_active', false);

    expect(driverTokenWorks($token))->toBeFalse();
    chooseDriverPin('+2348012345678', $unusedCode, '593726')->assertUnprocessable();
    deliveryRequest('POST', "drivers/{$driver['id']}/setup-codes", as: $this->owner)->assertConflict()->assertJsonPath('code', 'driver_deactivated');

    deliveryRequest('POST', "drivers/{$driver['id']}/reactivate", as: $this->owner)->assertOk()->assertJsonPath('data.is_active', true);
    deliveryRequest('POST', "drivers/{$driver['id']}/setup-codes", as: $this->owner)->assertCreated();
});

it('shows staff everything held about a driver, without the PIN, and erases it with the staff member\'s password', function (): void {
    $driver = addDriverWithCode($this->owner);
    $token = chooseDriverPin('+2348012345678', $driver['code'], '482915')->json('data.token');

    deliveryRequest('GET', "drivers/{$driver['id']}/personal-data", as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.account.0.phone', '+2348012345678')
        ->assertJsonPath('data.account.0.has_pin', true)
        ->assertJsonMissingPath('data.account.0.pin');

    deliveryRequest('POST', "drivers/{$driver['id']}/erasure", ['password' => 'wrong-password'], $this->owner)->assertUnprocessable();
    deliveryRequest('POST', "drivers/{$driver['id']}/erasure", ['password' => DELIVERY_STAFF_PASSWORD], $this->owner)->assertOk();

    deliveryRequest('GET', "drivers/{$driver['id']}", as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.is_erased', true);
    expect(driverTokenWorks($token))->toBeFalse();
    deliveryRequest('PUT', "drivers/{$driver['id']}", ['name' => 'Back'], $this->owner)->assertConflict()->assertJsonPath('code', 'driver_erased');
    deliveryRequest('POST', "drivers/{$driver['id']}/reactivate", as: $this->owner)->assertConflict()->assertJsonPath('code', 'driver_erased');

    // The phone number is free for a new account.
    addDriverWithCode($this->owner, '+2348012345678');
});

it('refuses other guards\' tokens on staff driver routes, and staff tokens on the driver app', function (): void {
    $driver = addDriverWithCode($this->owner);
    $driverToken = chooseDriverPin('+2348012345678', $driver['code'], '482915')->json('data.token');
    $customerToken = $this->store->run(static fn (): string => app(App\Shared\Auth\AccessTokenIssuer::class)->issue(App\Tenant\Customers\Models\Customer::factory()->create(), 'customer', 'test')->plainTextToken);

    foreach ([$driverToken, $customerToken] as $token) {
        forgetSignIns();
        $this->withToken($token)->getJson(storeUrl('first-store', '/api/v1/staff/delivery/drivers'))->assertUnauthorized();
        tenancy()->end();
    }

    forgetSignIns();
    $this->withToken(staffTokenFor($this->owner, 'first-store'))->putJson(storeUrl('first-store', '/api/v1/driver/auth/pin'), ['current_pin' => '482915', 'pin' => '593726'])->assertUnauthorized();
    tenancy()->end();
});

it('never finds another store\'s driver', function (): void {
    $otherStore = createStore('second-store');
    $otherDriver = $otherStore->run(static fn (): Driver => Driver::factory()->create());

    deliveryRequest('GET', "drivers/{$otherDriver->public_id}", as: $this->owner)->assertNotFound();
    deliveryRequest('POST', "drivers/{$otherDriver->public_id}/setup-codes", as: $this->owner)->assertNotFound();
    deliveryRequest('GET', 'drivers', as: $this->owner)->assertJsonPath('meta.total', 0);
});

it('deletes setup codes once they have been expired for the retention period', function (): void {
    $old = addDriverWithCode($this->owner, '+2348012345671');
    $this->travel(72 + 25)->hours();
    $new = addDriverWithCode($this->owner, '+2348012345672');

    $this->store->run(static function () use ($new): void {
        PurgeExpiredRecords::dispatchSync();

        expect(DriverSetupCode::query()->with('driver')->get()->map(static fn (DriverSetupCode $code): string => $code->driver->public_id)->all())->toBe([$new['id']]);
    });
    expect($old['id'])->not->toBe($new['id']);
});
