<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Delivery\Actions\ChooseDriverPin;
use App\Tenant\Delivery\Exceptions\InvalidDriverSetupCodeException;
use App\Tenant\Delivery\Models\DriverSetupCode;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 10, Step 17: a driver chooses their own 6-digit PIN with a one-time
 * code from staff. Codes work once, expire, and are voided after 5 wrong tries,
 * counted under a lock so racing requests can't get more. Every failure gives
 * the same answer. A signed-in driver changes their PIN with the current one.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    config(['api.rate_limits.login' => 100]);
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);

    $this->store = createStore('first-store');
    allowDrivers($this->store->getTenantKey(), 10);
    $owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create(['password' => DELIVERY_STAFF_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $this->driver = addDriverWithCode($owner);
});

afterEach(function (): void {
    deleteAllStores();
});

it('lets the driver choose their PIN with the code, signing them in, and then sign in with it', function (): void {
    $token = chooseDriverPin('+234 801 234 5678', $this->driver['code'], '482915')
        ->assertOk()
        ->json('data.token');

    expect(driverTokenWorks($token))->toBeTrue();
    signInAsDriver('+2348012345678', '482915')->assertOk();
});

it('accepts a code only once', function (): void {
    chooseDriverPin('+2348012345678', $this->driver['code'], '482915')->assertOk();

    chooseDriverPin('+2348012345678', $this->driver['code'], '593726')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_driver_setup_code');
    signInAsDriver('+2348012345678', '482915')->assertOk();
});

it('voids a code after 5 wrong tries, even when the right one comes next', function (): void {
    $wrongCode = $this->driver['code'] === '00000000' ? '00000001' : '00000000';

    foreach (range(1, 5) as $try) {
        chooseDriverPin('+2348012345678', $wrongCode, '482915')->assertUnprocessable()->assertJsonPath('code', 'invalid_driver_setup_code');
    }

    chooseDriverPin('+2348012345678', $this->driver['code'], '482915')->assertUnprocessable()->assertJsonPath('code', 'invalid_driver_setup_code');
    expect($this->store->run(static fn (): ?string => DriverSetupCode::query()->sole()->voided_at?->toIso8601String()))->not->toBeNull();
});

it('gives the same answer for an expired code and for a phone number without one', function (): void {
    chooseDriverPin('+2348099999999', $this->driver['code'], '482915')->assertUnprocessable()->assertJsonPath('code', 'invalid_driver_setup_code');

    $this->travel(73)->hours();

    chooseDriverPin('+2348012345678', $this->driver['code'], '482915')->assertUnprocessable()->assertJsonPath('code', 'invalid_driver_setup_code');
});

it('refuses PINs that aren\'t 6 digits, and the PINs anyone would guess first', function (string $pin): void {
    chooseDriverPin('+2348012345678', $this->driver['code'], $pin)->assertUnprocessable()->assertJsonValidationErrors('pin');
})->with(['12345', '1234567', '12a456', '123456', '111111', '987654', '012345']);

it('counts a wrong try under a lock on the code, so a racing request waits instead of getting a free try', function (): void {
    $wrongCode = $this->driver['code'] === '99999999' ? '99999998' : '99999999';

    $this->store->run(static function () use ($wrongCode): void {
        $setupCode = DriverSetupCode::query()->sole();
        $other = SecondConnection::open()->holdRowLock($setupCode);

        expect($other->blocks(static fn () => app(ChooseDriverPin::class)->handle('+2348012345678', $wrongCode, '482915', 'test')))->toBeTrue();

        $other->commit();
        expect(static fn () => app(ChooseDriverPin::class)->handle('+2348012345678', $wrongCode, '482915', 'test'))
            ->toThrow(InvalidDriverSetupCodeException::class)
            ->and(DriverSetupCode::query()->sole()->failed_attempts)->toBe(1);
    });
});

it('lets a signed-in driver change their PIN with the current one, signing out only their other devices', function (): void {
    $current = chooseDriverPin('+2348012345678', $this->driver['code'], '482915')->json('data.token');
    $other = signInAsDriver('+2348012345678', '482915')->json('data.token');

    driverAppRequest('PUT', 'auth/pin', ['current_pin' => '000000', 'pin' => '593726'], $current)->assertUnprocessable();
    driverAppRequest('PUT', 'auth/pin', ['current_pin' => '482915', 'pin' => '123456'], $current)->assertUnprocessable()->assertJsonValidationErrors('pin');
    driverAppRequest('PUT', 'auth/pin', ['current_pin' => '482915', 'pin' => '482915'], $current)->assertUnprocessable()->assertJsonValidationErrors('pin');
    driverAppRequest('PUT', 'auth/pin', ['current_pin' => '482915', 'pin' => '593726'], $current)->assertNoContent();

    expect(driverTokenWorks($current))->toBeTrue()
        ->and(driverTokenWorks($other))->toBeFalse();
    signInAsDriver('+2348012345678', '593726')->assertOk();
});

it('pauses PIN changes after 5 wrong current PINs, so a stolen token can\'t guess the PIN', function (): void {
    $token = chooseDriverPin('+2348012345678', $this->driver['code'], '482915')->json('data.token');

    foreach (range(1, 5) as $try) {
        driverAppRequest('PUT', 'auth/pin', ['current_pin' => '000000', 'pin' => '593726'], $token)->assertUnprocessable();
    }

    driverAppRequest('PUT', 'auth/pin', ['current_pin' => '482915', 'pin' => '593726'], $token)
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_incorrect_attempts');
});

it('never accepts a code from another store', function (): void {
    createStore('second-store');

    chooseDriverPin('+2348012345678', $this->driver['code'], '482915', 'second-store')->assertUnprocessable()->assertJsonPath('code', 'invalid_driver_setup_code');
});
