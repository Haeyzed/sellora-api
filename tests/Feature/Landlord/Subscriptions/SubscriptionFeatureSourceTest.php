<?php

declare(strict_types=1);

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Actions\ChangeSubscriptionPlan;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Subscriptions\Models\TenantFeature;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Shared\Features\FeatureState;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $registry = new FeatureRegistry;
    $registry->register(new FeatureDefinition('loyalty', FeatureKind::Module));
    $registry->register(new FeatureDefinition('gift_cards', FeatureKind::Module));
    $registry->register(new FeatureDefinition('whatsapp', FeatureKind::Integration));
    app()->instance(FeatureRegistry::class, $registry);

    $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00:00'));
    $this->store = createStoreRecord();
    $this->growthPlan = Plan::factory()->withFeatures('loyalty', 'gift_cards')->withLimits(['products' => 5000, 'locations' => null])->create();
    $this->starterPlan = Plan::factory()->withFeatures('gift_cards', 'whatsapp')->withLimits(['products' => 250])->create();
});

it('gives a store the modules, integrations and limits of its plan', function (): void {
    subscribeTestStoreTo($this->growthPlan);

    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Enabled)
        ->and(subscribedFeatureState('whatsapp'))->toBe(FeatureState::Unavailable)
        ->and(subscribedLimit('products'))->toBe(5000)
        ->and(subscribedLimit('locations'))->toBeNull()
        ->and(subscribedLimit('staff_accounts'))->toBe(0);
});

it('gives a store without a subscription nothing', function (): void {
    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Unavailable)
        ->and(subscribedLimit('products'))->toBe(0);
});

it('locks features a downgrade removes, keeps shared ones and enables new ones, effective immediately', function (): void {
    $subscription = subscribeTestStoreTo($this->growthPlan);
    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Enabled);

    app(ChangeSubscriptionPlan::class)->handle($subscription, $this->starterPlan);

    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Locked)
        ->and(subscribedFeatureState('gift_cards'))->toBe(FeatureState::Enabled)
        ->and(subscribedFeatureState('whatsapp'))->toBe(FeatureState::Enabled)
        ->and(subscribedLimit('products'))->toBe(250);
    $this->assertDatabaseHas('tenant_features', ['tenant_id' => $this->store->id, 'feature_key' => 'loyalty']);
    $this->assertDatabaseMissing('tenant_features', ['tenant_id' => $this->store->id, 'feature_key' => 'gift_cards']);
});

it('unlocks a feature when the store moves back to a plan that includes it', function (): void {
    $subscription = subscribeTestStoreTo($this->growthPlan);
    app(ChangeSubscriptionPlan::class)->handle($subscription, $this->starterPlan);

    app(ChangeSubscriptionPlan::class)->handle($subscription, $this->growthPlan);

    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Enabled);
});

it('locks, rather than hides, a feature added to the plan after the store subscribed, when the store downgrades', function (): void {
    $subscription = subscribeTestStoreTo($this->starterPlan);
    $this->starterPlan->features()->create(['feature_key' => 'loyalty']);
    $basicPlan = Plan::factory()->withFeatures('gift_cards')->create();

    app(ChangeSubscriptionPlan::class)->handle($subscription, $basicPlan);

    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Locked);
});

it('shows a feature the merchant switched off as disabled', function (): void {
    subscribeTestStoreTo($this->growthPlan);
    TenantFeature::query()->create(['tenant_id' => $this->store->id, 'feature_key' => 'loyalty', 'disabled_by_merchant_at' => now()]);

    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Disabled);
});

it('applies a store\'s own limit instead of its plan\'s until the override expires', function (): void {
    subscribeTestStoreTo($this->starterPlan);
    TenantLimitOverride::query()->create([
        'tenant_id' => $this->store->id,
        'limit_key' => 'products',
        'limit_value' => 1000,
        'expires_at' => CarbonImmutable::parse('2026-10-07 12:00:00'),
    ]);
    expect(subscribedLimit('products'))->toBe(1000);

    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:01'));

    expect(subscribedLimit('products'))->toBe(250);
});

it('never stores an override whose value is missing without the unlimited flag', function (): void {
    subscribeTestStoreTo($this->starterPlan);

    // Its own transaction, so the refused insert doesn't abort the test's.
    $insertWithoutValue = fn () => TenantLimitOverride::query()->getConnection()->transaction(fn (): TenantLimitOverride => TenantLimitOverride::query()->create([
        'tenant_id' => $this->store->id,
        'limit_key' => 'products',
        'limit_value' => null,
    ]));

    expect($insertWithoutValue)->toThrow(QueryException::class, 'tenant_limit_overrides_unlimited_is_explicit')
        ->and(subscribedLimit('products'))->toBe(250);
});

it('reads an override missing its value as zero, never unlimited, if one ever got past the database', function (): void {
    $override = new TenantLimitOverride(['limit_key' => 'products', 'limit_value' => null, 'is_unlimited' => false]);

    expect($override->effectiveLimit())->toBe(0)
        ->and((new TenantLimitOverride(['limit_key' => 'products', 'limit_value' => null, 'is_unlimited' => true]))->effectiveLimit())->toBeNull();
});

it('decides whether an unpaid or cancelled store keeps access', function (array $subscriptionAttributes, FeatureState $expectedState): void {
    subscribeTestStoreTo($this->growthPlan, $subscriptionAttributes);

    expect(subscribedFeatureState('loyalty'))->toBe($expectedState);
})->with([
    'trialing' => [['status' => SubscriptionStatus::Trialing, 'trial_ends_at' => '2026-10-20 12:00:00'], FeatureState::Enabled],
    'unpaid within the 7-day grace period' => [['status' => SubscriptionStatus::PastDue, 'past_due_at' => '2026-09-30 12:00:01'], FeatureState::Enabled],
    'unpaid past the grace period' => [['status' => SubscriptionStatus::PastDue, 'past_due_at' => '2026-09-29 12:00:00'], FeatureState::Suspended],
    'unpaid with no date to count from' => [['status' => SubscriptionStatus::PastDue], FeatureState::Suspended],
    'cancelled, paid period not over' => [['status' => SubscriptionStatus::Cancelled, 'ends_at' => '2026-10-31 00:00:00'], FeatureState::Enabled],
    'cancelled, paid period over' => [['status' => SubscriptionStatus::Cancelled, 'ends_at' => '2026-10-06 11:59:59'], FeatureState::Suspended],
    'suspended by the platform' => [['status' => SubscriptionStatus::Suspended], FeatureState::Suspended],
]);

it('suspends a store as soon as its grace period ends, even with its plan cached', function (): void {
    subscribeTestStoreTo($this->growthPlan, ['status' => SubscriptionStatus::PastDue, 'past_due_at' => '2026-09-30 12:00:00']);
    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Enabled);

    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:01'));

    expect(subscribedFeatureState('loyalty'))->toBe(FeatureState::Suspended);
});

it('never lets one store\'s plan or overrides affect another store', function (): void {
    subscribeTestStoreTo($this->growthPlan);
    TenantLimitOverride::query()->create(['tenant_id' => $this->store->id, 'limit_key' => 'products', 'limit_value' => null, 'is_unlimited' => true]);
    $otherStore = createStoreRecord();
    Subscription::factory()->create(['tenant_id' => $otherStore->id, 'plan_id' => $this->starterPlan->id]);

    expect(app(Features::class)->stateFor($otherStore->id, 'loyalty'))->toBe(FeatureState::Unavailable)
        ->and(app(Features::class)->limitFor($otherStore->id, 'products'))->toBe(250)
        ->and(subscribedLimit('products'))->toBeNull();
});
