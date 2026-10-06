<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\StoreClosedNotification;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Database\Seeders\Landlord\PlatformPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

/*
 * Section 9.1: a store is closed by a platform admin or by its owner
 * (password, plus a code with 2FA). Closing records the status before it,
 * signs everyone out (staff, customers and drivers) and sets a purge date 90
 * days away; the owner is emailed, and reminded 7 days before. Restoring is
 * for platform admins, only from Closed, and returns the store to the status
 * it had, so closing and restoring can never lift a suspension.
 */
uses(DatabaseTruncation::class);

const CLOSING_OWNER_PASSWORD = 'a-long-closing-owner-password';

beforeEach(function (): void {
    Notification::fake();
    $this->seed(PlatformPermissionSeeder::class);
    $this->storeManager = closingAdminWith('stores.view', 'stores.manage');

    $this->store = createStore('closing-store');
    $this->store->update(['owner_email' => 'owner@closing.example']);
    $this->storeOwner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create(['password' => CLOSING_OWNER_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

function closingAdminWith(string ...$permissionNames): PlatformAdmin
{
    $role = Role::findOrCreate('Closing role with '.implode(', ', $permissionNames), PlatformAdmin::GUARD);
    $role->syncPermissions($permissionNames);

    $platformAdmin = PlatformAdmin::factory()->create();
    $platformAdmin->assignRole($role);
    enableTwoFactor($platformAdmin);

    return $platformAdmin;
}

/**
 * @param  array<string, mixed>  $data
 */
function storeClosure(string $method, Tenant $store, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($as ?? test()->storeManager, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->json($method, centralUrl("/api/v1/platform/stores/{$store->public_id}/closure"), $data);
    forgetSignIns();

    return $response;
}

/**
 * Signs a store account in and returns its token, as each kind of account would.
 */
function closingStoreToken(Tenant $store, StaffMember|Customer|Driver $account, string $guard): string
{
    return $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($account, $guard, 'test')->plainTextToken);
}

/**
 * The owner closing their own store from the store's dashboard.
 *
 * @param  array<string, mixed>  $data
 */
function closeOwnStore(array $data, ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = closingStoreToken(test()->store, $as ?? test()->storeOwner, StaffMember::GUARD);
    $response = test()->withToken($token)->postJson(storeUrl('closing-store', '/api/v1/staff/store/closure'), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @return array<string, int>
 */
function closingStoreTokenCounts(Tenant $store): array
{
    return $store->run(static fn (): array => PersonalAccessToken::query()
        ->selectRaw('tokenable_type, count(*) as total')
        ->groupBy('tokenable_type')
        ->orderBy('tokenable_type')
        ->pluck('total', 'tokenable_type')
        ->map(static fn (mixed $total): int => (int) $total)
        ->all());
}

it('closes a store: it stops opening, everyone is signed out, and the owner is emailed the purge date', function (): void {
    [$customer, $driver] = $this->store->run(static fn (): array => [Customer::factory()->create(), Driver::factory()->create()]);
    $staffToken = closingStoreToken($this->store, $this->storeOwner, StaffMember::GUARD);
    closingStoreToken($this->store, $customer, Customer::GUARD);
    closingStoreToken($this->store, $driver, Driver::GUARD);
    expect(closingStoreTokenCounts($this->store))->toBe(['customer' => 1, 'driver' => 1, 'staff_member' => 1]);

    storeClosure('POST', $this->store, ['reason' => 'The owner asked by email'])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closure.reason', 'The owner asked by email')
        ->assertJsonPath('data.closure.closed_by.type', 'platform_admin')
        ->assertJsonPath('data.closure.status_before_closing', 'active');

    $store = $this->store->refresh();
    expect($store->status)->toBe(TenantStatus::Closed)
        ->and($store->status_before_closing)->toBe(TenantStatus::Active)
        ->and($store->purge_after?->toDateString())->toBe(now()->addDays(90)->toDateString())
        ->and(closingStoreTokenCounts($store))->toBe([]);

    $this->withToken($staffToken)->getJson(storeUrl('closing-store', '/api/v1/staff/auth/me'))
        ->assertServiceUnavailable()->assertJsonPath('code', 'store_unavailable');
    tenancy()->end();

    Notification::assertSentTo(new AnonymousNotifiable, StoreClosedNotification::class, static fn (StoreClosedNotification $notification, array $_channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'owner@closing.example'
        && ! $notification->isReminder
        && $notification->purgeAfter()->toDateString() === now()->addDays(90)->toDateString());
    expect(Activity::query()->where('event', 'store_closed')->where('subject_id', $store->id)->exists())->toBeTrue();
});

it('returns a restored store to the status it had, so closing never lifts a suspension', function (TenantStatus $statusBeforeClosing): void {
    $this->store->update(['status' => $statusBeforeClosing]);

    storeClosure('POST', $this->store, ['reason' => 'Closing to test restoring'])->assertOk();
    storeClosure('DELETE', $this->store)->assertOk()->assertJsonPath('data.status', $statusBeforeClosing->value)->assertJsonPath('data.closure', null);

    $store = $this->store->refresh();
    expect($store->status)->toBe($statusBeforeClosing)
        ->and($store->status_before_closing)->toBeNull()
        ->and($store->purge_after)->toBeNull();
})->with([
    'active' => [TenantStatus::Active],
    'suspended' => [TenantStatus::Suspended],
]);

it('signs out anyone left signed in from before the store closed when it is restored', function (): void {
    storeClosure('POST', $this->store, ['reason' => 'Closing to test restoring'])->assertOk();
    closingStoreToken($this->store, $this->storeOwner, StaffMember::GUARD);

    storeClosure('DELETE', $this->store)->assertOk();

    expect(closingStoreTokenCounts($this->store))->toBe([]);
});

it('closes and restores a store whose setup failed without touching a database', function (): void {
    $failed = Tenant::factory()->create(['status' => TenantStatus::ProvisioningFailed]);

    storeClosure('POST', $failed, ['reason' => 'Abandoned sign-up'])->assertOk()->assertJsonPath('data.status', 'closed');
    storeClosure('DELETE', $failed)->assertOk()->assertJsonPath('data.status', 'provisioning_failed');
});

it('closes only open, suspended or failed stores, and restores only closed ones', function (): void {
    $provisioning = Tenant::factory()->provisioning()->create();
    storeClosure('POST', $provisioning, ['reason' => 'Too early'])->assertConflict()->assertJsonPath('code', 'store_status_conflict');

    storeClosure('DELETE', $this->store)->assertConflict()->assertJsonPath('code', 'store_status_conflict');

    storeClosure('POST', $this->store, ['reason' => 'First closing'])->assertOk();
    storeClosure('POST', $this->store, ['reason' => 'Second closing'])->assertConflict();
});

it('needs the stores.manage permission and a reason', function (): void {
    $viewer = closingAdminWith('stores.view');

    storeClosure('POST', $this->store, ['reason' => 'Not allowed'], $viewer)->assertForbidden();
    storeClosure('POST', $this->store)->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect($this->store->refresh()->status)->toBe(TenantStatus::Active);
});

it('lets the owner close their own store with their password, signing them out too', function (): void {
    $staffToken = closingStoreToken($this->store, $this->storeOwner, StaffMember::GUARD);

    closeOwnStore(['current_password' => 'not-the-password'])->assertUnprocessable()->assertJsonPath('code', 'current_password_incorrect');
    expect($this->store->refresh()->status)->toBe(TenantStatus::Active);

    closeOwnStore(['current_password' => CLOSING_OWNER_PASSWORD, 'reason' => 'Moving on'])
        ->assertOk()
        ->assertJsonPath('data.purge_after', fn (string $purgeAfter): bool => str_starts_with($purgeAfter, now()->addDays(90)->toDateString()));

    $store = $this->store->refresh();
    expect($store->status)->toBe(TenantStatus::Closed)
        ->and($store->closed_by_type)->toBe('staff_member')
        ->and($store->closed_by_id)->toBe($this->storeOwner->public_id)
        ->and($store->closure_reason)->toBe('Moving on')
        ->and(closingStoreTokenCounts($store))->toBe([])
        ->and(Activity::query()->where('event', 'store_closed')->where('subject_id', $store->id)->whereNull('causer_type')->whereNull('causer_id')->exists())->toBeTrue()
        ->and($store->run(static fn (): bool => Activity::query()->where('event', 'store_closed')->exists()))->toBeTrue();

    $this->withToken($staffToken)->getJson(storeUrl('closing-store', '/api/v1/staff/auth/me'))->assertServiceUnavailable();
    tenancy()->end();
});

it('needs a code when the owner uses two-factor authentication', function (): void {
    $secret = $this->store->run(fn (): string => enableTwoFactor(StaffMember::query()->findOrFail($this->storeOwner->id)));

    closeOwnStore(['current_password' => CLOSING_OWNER_PASSWORD])->assertUnprocessable()->assertJsonPath('code', 'two_factor_code_invalid');
    expect($this->store->refresh()->status)->toBe(TenantStatus::Active);

    closeOwnStore(['current_password' => CLOSING_OWNER_PASSWORD, 'code' => twoFactorCode($secret)])->assertOk();
    expect($this->store->refresh()->status)->toBe(TenantStatus::Closed);
});

it('lets only the owner close the store, whatever their roles', function (): void {
    $manager = $this->store->run(static function (): StaffMember {
        $everything = Role::findOrCreate('Everything', StaffMember::GUARD);
        $everything->syncPermissions(Spatie\Permission\Models\Permission::query()->where('guard_name', StaffMember::GUARD)->get());
        $manager = StaffMember::factory()->create(['password' => CLOSING_OWNER_PASSWORD]);
        $manager->assignRole($everything);

        return $manager;
    });

    closeOwnStore(['current_password' => CLOSING_OWNER_PASSWORD], $manager)->assertForbidden();
    expect($this->store->refresh()->status)->toBe(TenantStatus::Active);
});

it('never records a closed store without the status to restore it to', function (): void {
    $closeWithoutStatusBefore = fn () => Tenant::query()->getConnection()->transaction(fn () => Tenant::query()->whereKey($this->store->id)->update(['status' => TenantStatus::Closed, 'closed_at' => now(), 'purge_after' => now()->addDays(90)]));

    expect($closeWithoutStatusBefore)->toThrow(QueryException::class, 'tenants_closure_is_complete');
});

it('reminds the owner once, 7 days before the purge date', function (): void {
    storeClosure('POST', $this->store, ['reason' => 'Testing reminders'])->assertOk();

    $this->travel(82)->days();
    expect(Artisan::call('stores:send-purge-reminders'))->toBe(0);
    Notification::assertNotSentTo(new AnonymousNotifiable, StoreClosedNotification::class, static fn (StoreClosedNotification $notification): bool => $notification->isReminder);

    $this->travel(2)->days();
    Artisan::call('stores:send-purge-reminders');
    Artisan::call('stores:send-purge-reminders');

    Notification::assertSentTimes(StoreClosedNotification::class, 2);
    expect($this->store->refresh()->purge_reminder_sent_at)->not->toBeNull();
});
