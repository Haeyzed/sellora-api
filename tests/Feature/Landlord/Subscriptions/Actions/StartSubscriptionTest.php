<?php

declare(strict_types=1);

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Actions\StartSubscription;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Subscriptions\Exceptions\PlanNotAvailableException;
use App\Landlord\Subscriptions\Exceptions\SubscriptionAlreadyExistsException;
use App\Landlord\Subscriptions\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->store = createStoreRecord();
    $this->plan = Plan::factory()->create();
});

it('puts a new store on a trial of its first plan', function (): void {
    $trialEndsAt = CarbonImmutable::parse('2026-10-20 12:00:00');

    $subscription = app(StartSubscription::class)->handle($this->store, $this->plan, SubscriptionStatus::Trialing, $trialEndsAt);

    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'tenant_id' => $this->store->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
    ]);
    expect($subscription->trial_ends_at?->equalTo($trialEndsAt))->toBeTrue()
        ->and(Str::isUlid($subscription->public_id))->toBeTrue()
        ->and($subscription->getRouteKeyName())->toBe('public_id');
});

it('refuses a second subscription for the same store', function (): void {
    app(StartSubscription::class)->handle($this->store, $this->plan, SubscriptionStatus::Active);

    expect(fn () => app(StartSubscription::class)->handle($this->store, $this->plan, SubscriptionStatus::Active))
        ->toThrow(SubscriptionAlreadyExistsException::class);
    expect(Subscription::query()->where('tenant_id', $this->store->id)->count())->toBe(1);
});

it('refuses a retired plan', function (): void {
    $retiredPlan = Plan::factory()->create(['is_active' => false]);

    expect(fn () => app(StartSubscription::class)->handle($this->store, $retiredPlan, SubscriptionStatus::Active))
        ->toThrow(PlanNotAvailableException::class);
    $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $this->store->id]);
});

it('refuses a trial without an end date, an active subscription with one, or any other starting status', function (SubscriptionStatus $status, ?string $trialEndsAt): void {
    $trialEnd = $trialEndsAt === null ? null : CarbonImmutable::parse($trialEndsAt);

    expect(fn () => app(StartSubscription::class)->handle($this->store, $this->plan, $status, $trialEnd))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'trial without an end' => [SubscriptionStatus::Trialing, null],
    'active with a trial end' => [SubscriptionStatus::Active, '2026-10-20 12:00:00'],
    'starting past due' => [SubscriptionStatus::PastDue, null],
]);
