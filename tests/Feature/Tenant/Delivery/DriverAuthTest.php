<?php

declare(strict_types=1);

use App\Tenant\Delivery\Models\Driver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Testing\TestResponse;

uses(DatabaseTruncation::class);

beforeEach(function (): void {
    config(['api.rate_limits.login' => 100]);
    $this->store = createStore('first-store');
    $this->driver = $this->store->run(static fn (): Driver => Driver::factory()->create(['phone' => '+2348012345678', 'pin' => '482915']));
});

afterEach(function (): void {
    deleteAllStores();
});

function signInAsDriver(string $phone, string $pin): TestResponse
{
    $response = test()->postJson(storeUrl('first-store', '/api/v1/driver/auth/tokens'), ['phone' => $phone, 'pin' => $pin]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

it('signs a driver in with their phone number in any international format and their PIN', function (string $typedPhone): void {
    $token = signInAsDriver($typedPhone, '482915')->assertOk()->json('data.token');

    $this->withToken($token)->getJson(storeUrl('first-store', '/api/v1/driver/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->driver->public_id)
        ->assertJsonPath('data.phone', '+2348012345678')
        ->assertJsonMissingPath('data.pin');
})->with(['+2348012345678', '+234 801 234 5678', '+234-801-234-5678']);

it('stores the PIN hashed, never as typed', function (): void {
    $storedPin = $this->store->run(static fn (): string => (string) Driver::query()->value('pin'));

    expect($storedPin)->not->toBe('482915')
        ->and(password_verify('482915', $storedPin))->toBeTrue();
});

it('refuses a phone number without a country code, or a PIN that isn\'t 4 to 6 digits', function (string $phone, string $pin, string $invalidField): void {
    signInAsDriver($phone, $pin)->assertUnprocessable()->assertJsonValidationErrors($invalidField);
})->with([
    'no country code' => ['08012345678', '482915', 'phone'],
    'PIN with letters' => ['+2348012345678', '48a915', 'pin'],
    'PIN too long' => ['+2348012345678', '4829150', 'pin'],
]);

it('pauses sign-in after 5 wrong PINs, even with the right PIN, so short PINs can\'t be guessed', function (): void {
    foreach (['000000', '111111', '222222', '333333', '444444'] as $wrongPin) {
        signInAsDriver('+2348012345678', $wrongPin)->assertUnprocessable();
    }

    signInAsDriver('+2348012345678', '482915')
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'sign_in_temporarily_locked');
});

it('locks unknown phone numbers the same way, so a lockout reveals nothing about who is a driver', function (): void {
    foreach (['000000', '111111', '222222', '333333', '444444'] as $wrongPin) {
        signInAsDriver('+2348099999999', $wrongPin)->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
    }

    signInAsDriver('+2348099999999', '000000')->assertTooManyRequests();
});

it('refuses a deactivated driver only after the right PIN', function (): void {
    $this->store->run(static fn (): bool => Driver::query()->update(['is_active' => false]) === 1);

    signInAsDriver('+2348012345678', '482915')->assertForbidden()->assertJsonPath('code', 'account_deactivated');
});
