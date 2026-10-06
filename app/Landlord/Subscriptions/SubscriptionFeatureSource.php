<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions;

use App\Landlord\Plans\Models\PlanFeature;
use App\Landlord\Plans\Models\PlanLimit;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Subscriptions\Models\TenantFeature;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureSnapshot;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tells Features what a store's subscription gives it: the plan's modules, integrations and limits, the store's overrides, and whether it is suspended.
 */
final readonly class SubscriptionFeatureSource implements FeatureSource
{
    public function __construct(private ConfigRepository $config) {}

    /**
     * A store without a subscription gets nothing: every feature unavailable and every limit zero.
     */
    public function snapshotFor(string $tenantId): FeatureSnapshot
    {
        $subscription = Subscription::query()
            ->with(['plan.features', 'plan.limits'])
            ->where('tenant_id', $tenantId)
            ->first();

        if ($subscription === null) {
            return FeatureSnapshot::withoutPlan();
        }

        $now = CarbonImmutable::now();
        $tenantFeatures = TenantFeature::query()->where('tenant_id', $tenantId)->get();
        $limitOverrides = TenantLimitOverride::query()->where('tenant_id', $tenantId)->get();
        $accessEndsAt = $this->accessEndsAt($subscription);

        return new FeatureSnapshot(
            isStoreSuspended: $this->isSuspended($subscription, $accessEndsAt, $now),
            entitledFeatureKeys: array_values($subscription->plan->features->map(static fn (PlanFeature $feature): string => $feature->feature_key)->all()),
            removedFeatureKeys: $this->featureKeysWhere($tenantFeatures, static fn (TenantFeature $feature): bool => $feature->removed_from_plan_at !== null),
            merchantDisabledFeatureKeys: $this->featureKeysWhere($tenantFeatures, static fn (TenantFeature $feature): bool => $feature->disabled_by_merchant_at !== null),
            limits: $this->limits($subscription, $limitOverrides, $now),
            changesAt: $this->earliestUpcoming([
                $accessEndsAt,
                ...$limitOverrides->map(static fn (TenantLimitOverride $override): ?CarbonImmutable => $override->expires_at)->all(),
            ], $now),
        );
    }

    /**
     * When the store loses access unless something changes: the end of the grace period for an unpaid subscription, or the end date of a cancelled one.
     */
    private function accessEndsAt(Subscription $subscription): ?CarbonImmutable
    {
        return match ($subscription->status) {
            SubscriptionStatus::PastDue => $subscription->past_due_at?->addDays((int) $this->config->get('features.past_due_grace_period_in_days', 7)),
            SubscriptionStatus::Cancelled => $subscription->ends_at,
            default => null,
        };
    }

    /**
     * An unpaid or cancelled subscription with no date to count from is treated as ended, so bad data never grants access.
     */
    private function isSuspended(Subscription $subscription, ?CarbonImmutable $accessEndsAt, CarbonImmutable $now): bool
    {
        return match ($subscription->status) {
            SubscriptionStatus::Trialing, SubscriptionStatus::Active => false,
            SubscriptionStatus::Suspended => true,
            SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled => $accessEndsAt === null || $accessEndsAt->lessThanOrEqualTo($now),
        };
    }

    /**
     * @param  Collection<int, TenantFeature>  $tenantFeatures
     * @param  Closure(TenantFeature): bool  $condition
     * @return list<string>
     */
    private function featureKeysWhere(Collection $tenantFeatures, Closure $condition): array
    {
        return array_values($tenantFeatures->filter($condition)->map(static fn (TenantFeature $feature): string => $feature->feature_key)->all());
    }

    /**
     * The plan's limits, replaced by any of the store's overrides that haven't expired.
     *
     * @param  Collection<int, TenantLimitOverride>  $limitOverrides
     * @return array<string, int|null>
     */
    private function limits(Subscription $subscription, Collection $limitOverrides, CarbonImmutable $now): array
    {
        $limits = [];

        foreach ($subscription->plan->limits as $planLimit) {
            /** @var PlanLimit $planLimit */
            $limits[$planLimit->limit_key] = $planLimit->limit_value;
        }

        foreach ($limitOverrides as $limitOverride) {
            if ($limitOverride->expires_at === null || $limitOverride->expires_at->greaterThan($now)) {
                $limits[$limitOverride->limit_key] = $limitOverride->effectiveLimit();
            }
        }

        return $limits;
    }

    /**
     * @param  list<CarbonImmutable|null>  $moments
     */
    private function earliestUpcoming(array $moments, CarbonImmutable $now): ?CarbonImmutable
    {
        $upcomingMoments = array_filter(
            $moments,
            static fn (?CarbonImmutable $moment): bool => $moment !== null && $moment->greaterThan($now),
        );

        return $upcomingMoments === [] ? null : min($upcomingMoments);
    }
}
