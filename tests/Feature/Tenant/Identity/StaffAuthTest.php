<?php

declare(strict_types=1);

use App\Shared\Auth\PasswordResetLinkNotification;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

uses(DatabaseTruncation::class);

const STAFF_PASSWORD = 'a-long-staff-password';

beforeEach(function (): void {
    $this->store = createStore('first-store');
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create(['email' => 'owner@first-store.test', 'password' => STAFF_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

function signInAsStaff(string $subdomain, string $email = 'owner@first-store.test'): TestResponse
{
    $response = test()->postJson(storeUrl($subdomain, '/api/v1/staff/auth/tokens'), ['email' => $email, 'password' => STAFF_PASSWORD]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

it('signs a staff member in to their store and shows their roles', function (): void {
    $token = signInAsStaff('first-store')->assertOk()->json('data.token');

    $this->withToken($token)->getJson(storeUrl('first-store', '/api/v1/staff/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->owner->public_id)
        ->assertJsonPath('data.roles', [StaffRole::Owner->value]);
});

it('creates the built-in owner role in every new store, and lets owners do everything in their store', function (): void {
    $this->store->run(function (): void {
        $cashier = StaffMember::factory()->create();

        expect(Gate::forUser($this->owner->fresh())->allows('orders.refund'))->toBeTrue()
            ->and(Gate::forUser($cashier)->allows('orders.refund'))->toBeFalse();
    });
});

it('returns 401 when a staff token from one store is used on another store', function (): void {
    createStore('second-store');
    $firstStoreToken = signInAsStaff('first-store')->json('data.token');

    $this->withToken($firstStoreToken)->getJson(storeUrl('second-store', '/api/v1/staff/auth/me'))->assertUnauthorized();
});

it('never signs in a staff member of one store on another store', function (): void {
    createStore('second-store');

    signInAsStaff('second-store')->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
});

it('counts wrong passwords per store, so a lockout in one store never locks the same email in another', function (): void {
    config(['api.rate_limits.login' => 100]);
    $secondStore = createStore('second-store');
    $secondStore->run(static fn (): StaffMember => StaffMember::factory()->create(['email' => 'owner@first-store.test', 'password' => STAFF_PASSWORD]));

    foreach (range(1, 5) as $attempt) {
        $this->postJson(storeUrl('first-store', '/api/v1/staff/auth/tokens'), ['email' => 'owner@first-store.test', 'password' => 'wrong-'.$attempt]);
        tenancy()->end();
    }

    signInAsStaff('first-store')->assertTooManyRequests();
    signInAsStaff('second-store')->assertOk();
});

it('emails a reset link that points to the store domain the request came from', function (): void {
    Notification::fake();

    $this->postJson(storeUrl('first-store', '/api/v1/staff/auth/password-reset-links'), ['email' => 'owner@first-store.test'])->assertAccepted();

    $this->store->run(function (): void {
        Notification::assertSentTo($this->owner, PasswordResetLinkNotification::class, static fn (PasswordResetLinkNotification $notification): bool => str_starts_with($notification->resetUrl(), 'https://first-store.'.config('platform.domain').'/admin/reset-password?token='));
    });
});

it('serves staff sign-in on store domains only', function (): void {
    $this->postJson('/api/v1/staff/auth/tokens', ['email' => 'owner@first-store.test', 'password' => STAFF_PASSWORD])->assertNotFound();
});

it('keeps sign-in tokens in the store\'s own database', function (): void {
    signInAsStaff('first-store')->assertOk();

    expect($this->store->run(static fn (): int => PersonalAccessToken::query()->count()))->toBe(1);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});
