<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Models\LegalAcceptance;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Tenancy\Enums\StoreExportStatus;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Jobs\PurgeStore;
use App\Landlord\Tenancy\Models\Domain;
use App\Landlord\Tenancy\Models\ReleasedSubdomain;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\StoreSubdomains;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use Database\Seeders\Landlord\PlatformPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/*
 * Section 9.1: a closed store past its purge date is purged only while
 * tenancy.purge_enabled is on. The purge is resumable and safe to run twice:
 * it drops the database, deletes the store's files and exports, deletes its
 * domains and holds its subdomain for 365 days, clears the owner's personal
 * data and marks the store Purged, keeping subscriptions and legal
 * acceptances. Once a purge has started, the store can't be restored.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    Storage::fake('store_exports');
    config()->set('tenancy.purge_enabled', true);

    $this->purgedStore = createStore('purged-store');
    $this->purgedStore->update(['owner_name' => 'Pat Owner', 'owner_email' => 'pat@purged.example']);
    closeStoreForPurging($this->purgedStore, purgeAfter: now()->subDay());
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('tenant'.test()->purgedStore->id));
    deleteAllStores();
});

function closeStoreForPurging(Tenant $store, DateTimeInterface $purgeAfter): void
{
    $store->update([
        'status' => TenantStatus::Closed,
        'status_before_closing' => TenantStatus::Active,
        'closed_at' => now()->subDays(90),
        'closure_reason' => 'The owner wrote that they are moving abroad',
        'purge_after' => $purgeAfter,
    ]);
}

function purgedStoreDatabaseExists(Tenant $store): bool
{
    $databaseConfig = $store->database();

    return $databaseConfig->manager()->databaseExists((string) $databaseConfig->getName());
}

function restoreForPurgeTest(Tenant $store): TestResponse
{
    $role = Role::findOrCreate('Purge test store manager', PlatformAdmin::GUARD);
    $role->syncPermissions(['stores.view', 'stores.manage']);
    $platformAdmin = PlatformAdmin::factory()->create();
    $platformAdmin->assignRole($role);
    enableTwoFactor($platformAdmin);

    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($platformAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->deleteJson(centralUrl("/api/v1/platform/stores/{$store->public_id}/closure"));
    forgetSignIns();

    return $response;
}

it('purges nothing while purging is switched off', function (): void {
    config()->set('tenancy.purge_enabled', false);

    expect(Artisan::call('stores:purge-closed'))->toBe(0)
        ->and(Artisan::output())->toContain('switched off');

    dispatch(new PurgeStore($this->purgedStore->id));

    expect($this->purgedStore->refresh()->status)->toBe(TenantStatus::Closed)
        ->and(purgedStoreDatabaseExists($this->purgedStore))->toBeTrue();
});

it('deletes a closed store\'s data for good once its purge date has passed, keeping its contract and billing records', function (): void {
    $this->seed(PlatformPermissionSeeder::class);
    $store = $this->purgedStore;
    $store->run(static fn () => Storage::disk('local')->put('logos/logo.png', 'an image'));
    expect(File::isDirectory(storage_path('tenant'.$store->id)))->toBeTrue();

    $storeExport = new StoreExport(['disk' => 'store_exports', 'expires_at' => now()->addDays(7)]);
    $storeExport->forceFill(['tenant_id' => $store->id, 'requested_by_type' => 'platform_admin', 'requested_by_id' => 'someone', 'status' => StoreExportStatus::Ready, 'path' => "{$store->id}/export.zip"])->save();
    Storage::disk('store_exports')->put("{$store->id}/export.zip", 'zip');

    $subscription = Subscription::factory()->create(['tenant_id' => $store->id, 'plan_id' => Plan::factory()->create()->id]);
    $acceptance = new LegalAcceptance(['legal_document_id' => LegalDocument::factory()->inForce()->create()->id, 'accepted_by_name' => 'Pat Owner', 'accepted_by_email' => 'pat@purged.example', 'accepted_at' => now()]);
    $acceptance->forceFill(['tenant_id' => $store->id])->save();

    expect(Artisan::call('stores:purge-closed'))->toBe(0);

    $store->refresh();
    expect($store->status)->toBe(TenantStatus::Purged)
        ->and($store->purged_at)->not->toBeNull()
        ->and($store->owner_name)->toBeNull()
        ->and($store->owner_email)->toBeNull()
        ->and($store->closure_reason)->toBeNull()
        ->and(purgedStoreDatabaseExists($store))->toBeFalse()
        ->and(File::isDirectory(storage_path('tenant'.$store->id)))->toBeFalse()
        ->and(StoreExport::query()->count())->toBe(0)
        ->and(Domain::query()->where('tenant_id', $store->id)->exists())->toBeFalse()
        ->and(Subscription::query()->whereKey($subscription->id)->exists())->toBeTrue()
        ->and(LegalAcceptance::query()->whereKey($acceptance->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'store_purged')->where('subject_id', $store->id)->exists())->toBeTrue();
    Storage::disk('store_exports')->assertMissing("{$store->id}/export.zip");
});

it('never purges a store before its purge date, nor one that isn\'t closed', function (): void {
    closeStoreForPurging($this->purgedStore, purgeAfter: now()->addDay());
    $active = createStore('active-unpurged-store');

    Artisan::call('stores:purge-closed');
    dispatch(new PurgeStore($this->purgedStore->id));
    dispatch(new PurgeStore($active->id));

    expect($this->purgedStore->refresh()->status)->toBe(TenantStatus::Closed)
        ->and($active->refresh()->status)->toBe(TenantStatus::Active)
        ->and(purgedStoreDatabaseExists($this->purgedStore))->toBeTrue()
        ->and(purgedStoreDatabaseExists($active))->toBeTrue();
});

it('holds a purged store\'s subdomain for 365 days, so nobody takes over its old links', function (): void {
    Artisan::call('stores:purge-closed');

    expect(app(StoreSubdomains::class)->isTaken('purged-store'))->toBeTrue()
        ->and(ReleasedSubdomain::query()->where('subdomain', 'purged-store')->sole()->held_until->toDateString())->toBe(now()->addDays(365)->toDateString());

    $this->travel(366)->days();

    expect(app(StoreSubdomains::class)->isTaken('purged-store'))->toBeFalse();
});

it('carries on a purge that stopped halfway, even with purging switched off, and is safe to run twice', function (): void {
    app(App\Landlord\Tenancy\Services\StoreDatabase::class)->drop($this->purgedStore);
    $this->purgedStore->update(['status' => TenantStatus::Purging]);
    config()->set('tenancy.purge_enabled', false);

    dispatch(new PurgeStore($this->purgedStore->id));
    dispatch(new PurgeStore($this->purgedStore->id));

    expect($this->purgedStore->refresh()->status)->toBe(TenantStatus::Purged)
        ->and($this->purgedStore->owner_email)->toBeNull()
        ->and(Activity::query()->where('event', 'store_purged')->count())->toBe(1);
});

it('refuses to restore a store once its purge has started', function (TenantStatus $status): void {
    $this->seed(PlatformPermissionSeeder::class);
    $this->purgedStore->update(['status' => $status]);

    restoreForPurgeTest($this->purgedStore)->assertConflict()->assertJsonPath('code', 'store_status_conflict');
    expect($this->purgedStore->refresh()->status)->toBe($status);
})->with([
    'purging' => [TenantStatus::Purging],
    'purged' => [TenantStatus::Purged],
]);

it('allows missing owner details only on a purged store', function (): void {
    $clearOwner = fn () => Tenant::query()->getConnection()->transaction(fn () => Tenant::query()->whereKey($this->purgedStore->id)->update(['owner_email' => null]));

    expect($clearOwner)->toThrow(QueryException::class, 'tenants_owner_details_until_purged');

    $this->purgedStore->update(['status' => TenantStatus::Purged]);
    $clearOwner();
    expect($this->purgedStore->refresh()->owner_email)->toBeNull();
});
