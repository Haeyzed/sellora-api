<?php

declare(strict_types=1);

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Shared\Features\Features;
use App\Shared\Features\FeatureState;

/*
|--------------------------------------------------------------------------
| For SubscriptionFeatureSourceTest.php
|--------------------------------------------------------------------------
*/

function subscribeTestStoreTo(Plan $plan, array $attributes = []): Subscription
{
    return Subscription::factory()->create(['tenant_id' => test()->store->id, 'plan_id' => $plan->id, ...$attributes]);
}

function subscribedFeatureState(string $featureKey): FeatureState
{
    return app(Features::class)->stateFor(test()->store->id, $featureKey);
}

function subscribedLimit(string $limitKey): ?int
{
    return app(Features::class)->limitFor(test()->store->id, $limitKey);
}
