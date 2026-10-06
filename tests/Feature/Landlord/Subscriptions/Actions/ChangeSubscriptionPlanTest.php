<?php

declare(strict_types=1);

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Actions\ChangeSubscriptionPlan;
use App\Landlord\Subscriptions\Exceptions\PlanNotAvailableException;
use App\Landlord\Subscriptions\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->store = createStoreRecord();
    $this->currentPlan = Plan::factory()->withFeatures('loyalty')->create();
    $this->subscription = Subscription::factory()->create(['tenant_id' => $this->store->id, 'plan_id' => $this->currentPlan->id]);
});

it('moves the store to the new plan', function (): void {
    $newPlan = Plan::factory()->create();

    $subscription = app(ChangeSubscriptionPlan::class)->handle($this->subscription, $newPlan);

    expect($subscription->plan_id)->toBe($newPlan->id);
    $this->assertDatabaseHas('subscriptions', ['id' => $this->subscription->id, 'plan_id' => $newPlan->id]);
});

it('refuses a retired plan and leaves the subscription unchanged', function (): void {
    $retiredPlan = Plan::factory()->create(['is_active' => false]);

    expect(fn () => app(ChangeSubscriptionPlan::class)->handle($this->subscription, $retiredPlan))
        ->toThrow(PlanNotAvailableException::class);
    $this->assertDatabaseHas('subscriptions', ['id' => $this->subscription->id, 'plan_id' => $this->currentPlan->id]);
});

it('records nothing when the store is already on the plan', function (): void {
    app(ChangeSubscriptionPlan::class)->handle($this->subscription, $this->currentPlan);

    $this->assertDatabaseCount('tenant_features', 0);
});
