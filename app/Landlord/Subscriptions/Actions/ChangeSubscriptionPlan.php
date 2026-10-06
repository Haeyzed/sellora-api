<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Actions;

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Plans\Models\PlanFeature;
use App\Landlord\Subscriptions\Exceptions\PlanNotAvailableException;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Subscriptions\Models\TenantFeature;
use App\Shared\Features\Features;
use Carbon\CarbonImmutable;

/**
 * Moves a store to another plan (an upgrade or a downgrade), taking effect immediately.
 *
 * Features the new plan adds are usable at once. Features it removes become
 * locked: the store's existing data in them stays visible and exportable, but
 * nothing new can be created, and nothing is deleted. Moving back to a plan
 * that includes them unlocks them.
 */
final readonly class ChangeSubscriptionPlan
{
    public function __construct(private Features $features) {}

    /**
     * @throws PlanNotAvailableException When the new plan has been retired.
     */
    public function handle(Subscription $subscription, Plan $newPlan): Subscription
    {
        if (! $newPlan->is_active) {
            throw new PlanNotAvailableException($newPlan);
        }

        $subscription->getConnection()->transaction(function () use ($subscription, $newPlan): void {
            $lockedSubscription = Subscription::query()->with('plan.features')->lockForUpdate()->findOrFail($subscription->id);

            if ($lockedSubscription->plan_id === $newPlan->id) {
                return;
            }

            $this->recordRemovedFeatures($lockedSubscription, $newPlan);

            $lockedSubscription->plan()->associate($newPlan);
            $lockedSubscription->save();

            $subscription->getConnection()->afterCommit(fn () => $this->features->forget($lockedSubscription->tenant_id));
        });

        return $subscription->refresh();
    }

    /**
     * Records each feature the current plan has and the new plan doesn't, so it becomes locked rather than disappearing.
     */
    private function recordRemovedFeatures(Subscription $subscription, Plan $newPlan): void
    {
        $currentFeatureKeys = $subscription->plan->features->map(static fn (PlanFeature $feature): string => $feature->feature_key)->all();
        $newFeatureKeys = $newPlan->features()->pluck('feature_key')->all();
        $removedAt = CarbonImmutable::now();

        foreach (array_diff($currentFeatureKeys, $newFeatureKeys) as $removedFeatureKey) {
            TenantFeature::query()->updateOrCreate(
                ['tenant_id' => $subscription->tenant_id, 'feature_key' => $removedFeatureKey],
                ['removed_from_plan_at' => $removedAt],
            );
        }
    }
}
