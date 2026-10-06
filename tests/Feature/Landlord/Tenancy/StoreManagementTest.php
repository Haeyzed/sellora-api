<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Subscriptions\Models\FeatureGrant;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Jobs\ProvisionStore;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Shared\Features\FeatureState;
use Database\Seeders\Landlord\PlatformPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;

/*
 * Section 8 and 10: Sellora's team sees every store, suspends and reactivates
 * stores separately from billing, retries failed setups, and gives one store
 * features or limits outside its plan through Features, never by editing
 * plans. A grant that ends locks its feature like a downgrade.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    $this->seed(PlatformPermissionSeeder::class);

    $featureRegistry = new FeatureRegistry;
    $featureRegistry->register(new FeatureDefinition('loyalty', FeatureKind::Module));
    $featureRegistry->register(new FeatureDefinition('gift_cards', FeatureKind::Module));
    app()->instance(FeatureRegistry::class, $featureRegistry);

    $this->plan = Plan::factory()->withFeatures('gift_cards')->withLimits(['products' => 100])->create();
    $this->operator = platformAdminWith('stores.view', 'stores.manage', 'stores.grant');
});

afterEach(function (): void {
    deleteAllStores();
});

function platformAdminWith(string ...$permissionNames): PlatformAdmin
{
    $role = Role::findOrCreate('Role with '.implode(', ', $permissionNames), PlatformAdmin::GUARD);
    $role->syncPermissions($permissionNames);

    $platformAdmin = PlatformAdmin::factory()->create();
    $platformAdmin->assignRole($role);
    enableTwoFactor($platformAdmin);

    return $platformAdmin;
}

function subscribedStore(?string $subdomain = null): Tenant
{
    $store = $subdomain === null ? createStoreRecord() : createStore($subdomain);
    Subscription::factory()->create(['tenant_id' => $store->id, 'plan_id' => test()->plan->id, 'status' => SubscriptionStatus::Active]);

    return $store;
}

/**
 * @param  array<string, mixed>  $data
 */
function manageStores(string $method, string $path, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($as ?? test()->operator, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->json($method, centralUrl('/api/v1/platform/stores'.$path), $data);
    forgetSignIns();

    return $response;
}

function storeFeatureState(Tenant $store, string $featureKey): FeatureState
{
    return app(Features::class)->stateFor($store->id, $featureKey);
}

it('lists stores newest first, by status or matching a search', function (): void {
    $ada = subscribedStore();
    $ada->update(['name' => 'Ada Fabrics']);
    $ada->domains()->create(['domain' => Tenant::platformDomainFor('ada-fabrics')]);
    $this->travel(1)->minute();
    $suspended = subscribedStore();
    $suspended->update(['name' => 'Bola Shoes', 'status' => TenantStatus::Suspended]);

    manageStores('GET', '')->assertOk()->assertJsonPath('data.0.id', $suspended->public_id)->assertJsonPath('data.1.id', $ada->public_id);
    manageStores('GET', '?status=suspended')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bola Shoes');
    manageStores('GET', '?search=FABRICS')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.domains', ['ada-fabrics.'.config('platform.domain')]);
    manageStores('GET', '?search=100%25')->assertOk()->assertJsonCount(0, 'data');
    manageStores('GET', "/{$ada->public_id}")->assertOk()->assertJsonPath('data.plan.code', $this->plan->code);
});

it('suspends a store\'s features while still serving it, separately from billing', function (): void {
    $store = subscribedStore('ada-fabrics');

    manageStores('POST', "/{$store->public_id}/suspension", ['reason' => 'Card fraud reports'])
        ->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.suspension_reason', 'Card fraud reports');

    expect(storeFeatureState($store, 'gift_cards'))->toBe(FeatureState::Suspended);
    // Still served: staff can sign in to see why and export; a wrong password is the only refusal here.
    $this->postJson(storeUrl('ada-fabrics', '/api/v1/staff/auth/tokens'), ['email' => 'nobody@example.com', 'password' => 'not-a-real-password'])
        ->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
    tenancy()->end();
    manageStores('POST', "/{$store->public_id}/suspension", ['reason' => 'Again'])->assertConflict()->assertJsonPath('code', 'store_status_conflict');

    // An unpaid subscription keeps the store suspended after the platform lifts its own suspension.
    Subscription::query()->where('tenant_id', $store->id)->update(['status' => SubscriptionStatus::Suspended]);
    manageStores('DELETE', "/{$store->public_id}/suspension")->assertOk()->assertJsonPath('data.status', 'active');
    expect(storeFeatureState($store, 'gift_cards'))->toBe(FeatureState::Suspended);

    Subscription::query()->where('tenant_id', $store->id)->update(['status' => SubscriptionStatus::Active]);
    app(Features::class)->forget($store->id);
    expect(storeFeatureState($store, 'gift_cards'))->toBe(FeatureState::Enabled)
        ->and(Activity::query()->where('causer_id', (string) $this->operator->id)->pluck('event')->all())->toBe(['store_suspended', 'store_reactivated']);
});

it('retries setting up a store only after its setup failed', function (): void {
    Queue::fake();
    $store = subscribedStore();

    manageStores('POST', "/{$store->public_id}/provisioning-retries")->assertConflict();

    $store->update(['status' => TenantStatus::ProvisioningFailed]);
    manageStores('POST', "/{$store->public_id}/provisioning-retries")->assertAccepted()->assertJsonPath('data.status', 'provisioning');

    Queue::assertPushed(ProvisionStore::class, static fn (ProvisionStore $job): bool => $job->tenantId === $store->id);
});

it('grants a feature until a date, and locks it once the grant ends', function (): void {
    $store = subscribedStore();
    expect(storeFeatureState($store, 'loyalty'))->toBe(FeatureState::Unavailable);

    manageStores('POST', "/{$store->public_id}/feature-grants", ['feature' => 'loyalty', 'reason' => 'Launch month', 'expires_at' => now()->addDays(30)->toIso8601String()])
        ->assertCreated()->assertJsonPath('data.status', 'active');
    expect(storeFeatureState($store, 'loyalty'))->toBe(FeatureState::Enabled);

    manageStores('POST', "/{$store->public_id}/feature-grants", ['feature' => 'loyalty', 'reason' => 'Again'])
        ->assertConflict()->assertJsonPath('code', 'feature_already_granted');
    manageStores('POST', "/{$store->public_id}/feature-grants", ['feature' => 'not_installed', 'reason' => 'Typo'])->assertJsonValidationErrors('feature');

    $this->travel(31)->days();
    expect(storeFeatureState($store, 'loyalty'))->toBe(FeatureState::Locked);

    $grantId = manageStores('POST', "/{$store->public_id}/feature-grants", ['feature' => 'loyalty', 'reason' => 'Extended'])->assertCreated()->json('data.id');
    expect(storeFeatureState($store, 'loyalty'))->toBe(FeatureState::Enabled);

    manageStores('DELETE', "/{$store->public_id}/feature-grants/{$grantId}")->assertOk()->assertJsonPath('data.status', 'ended');
    manageStores('DELETE', "/{$store->public_id}/feature-grants/{$grantId}")->assertConflict()->assertJsonPath('code', 'feature_grant_not_active');
    expect(storeFeatureState($store, 'loyalty'))->toBe(FeatureState::Locked)
        ->and(storeFeatureState($store, 'gift_cards'))->toBe(FeatureState::Enabled);

    manageStores('GET', "/{$store->public_id}/feature-grants")->assertOk()->assertJsonCount(2, 'data');
    expect(Audit::query()->where('auditable_type', 'feature_grant')->where('user_id', $this->operator->id)->pluck('event')->all())
        ->toBe(['created', 'created', 'updated']);
});

it('only finds a grant inside its own store', function (): void {
    $store = subscribedStore();
    $otherStore = subscribedStore();
    $otherGrant = FeatureGrant::query()->create(['tenant_id' => $otherStore->id, 'feature_key' => 'loyalty', 'reason' => 'Other store']);

    manageStores('DELETE', "/{$store->public_id}/feature-grants/{$otherGrant->public_id}")->assertNotFound();
    expect($otherGrant->refresh()->isActive())->toBeTrue();
});

it('overrides a usage limit until a date, and the plan\'s limit applies again after', function (): void {
    $store = subscribedStore();

    manageStores('PUT', "/{$store->public_id}/limit-overrides/products", ['value' => 500, 'reason' => 'Launch', 'expires_at' => now()->addWeek()->toIso8601String()])
        ->assertCreated()->assertJsonPath('data.value', 500);
    expect(app(Features::class)->limitFor($store->id, 'products'))->toBe(500);

    manageStores('PUT', "/{$store->public_id}/limit-overrides/products", ['unlimited' => true, 'reason' => 'Unlimited for the launch'])
        ->assertOk()->assertJsonPath('data.unlimited', true)->assertJsonPath('data.value', null);
    expect(app(Features::class)->limitFor($store->id, 'products'))->toBeNull();

    manageStores('DELETE', "/{$store->public_id}/limit-overrides/products")->assertNoContent();
    expect(app(Features::class)->limitFor($store->id, 'products'))->toBe(100)
        ->and(TenantLimitOverride::query()->count())->toBe(0)
        ->and(Audit::query()->where('auditable_type', 'limit_override')->pluck('event')->all())->toBe(['created', 'updated', 'deleted']);

    manageStores('PUT', "/{$store->public_id}/limit-overrides/not_a_limit", ['value' => 1, 'reason' => 'Typo'])->assertNotFound();
});

it('never reads a missing or null value as unlimited: unlimited has to be sent on purpose', function (array $body): void {
    $store = subscribedStore();

    manageStores('PUT', "/{$store->public_id}/limit-overrides/products", [...$body, 'reason' => 'Launch'])->assertJsonValidationErrors('value');

    expect(TenantLimitOverride::query()->count())->toBe(0)
        ->and(app(Features::class)->limitFor($store->id, 'products'))->toBe(100);
})->with([
    'no value' => [[]],
    'a null value' => [['value' => null]],
    'a null value, unlimited false' => [['value' => null, 'unlimited' => false]],
    'unlimited false and no value' => [['unlimited' => false]],
    'an empty value' => [['value' => '']],
    'a value as well as unlimited' => [['value' => 5, 'unlimited' => true]],
]);

it('checks each permission separately', function (): void {
    $store = subscribedStore();
    $viewer = platformAdminWith(PlatformPermission::StoresView->value);

    manageStores('GET', "/{$store->public_id}", as: $viewer)->assertOk();
    manageStores('POST', "/{$store->public_id}/suspension", ['reason' => 'No'], as: $viewer)->assertForbidden();
    manageStores('POST', "/{$store->public_id}/feature-grants", ['feature' => 'loyalty', 'reason' => 'No'], as: $viewer)->assertForbidden();
    manageStores('PUT', "/{$store->public_id}/limit-overrides/products", ['value' => 1, 'reason' => 'No'], as: $viewer)->assertForbidden();
    manageStores('GET', '', as: platformAdminWith(PlatformPermission::LegalDocumentsManage->value))->assertForbidden();

    expect($store->refresh()->status)->toBe(TenantStatus::Active);
});
