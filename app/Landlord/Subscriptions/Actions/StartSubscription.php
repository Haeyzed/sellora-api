<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Actions;

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Subscriptions\Exceptions\PlanNotAvailableException;
use App\Landlord\Subscriptions\Exceptions\SubscriptionAlreadyExistsException;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Features;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Puts a new store on its first plan, either as a trial or as an active subscription.
 */
final readonly class StartSubscription
{
    public function __construct(private Features $features) {}

    /**
     * @param  CarbonImmutable|null  $trialEndsAt  Required for a trial, and only for a trial.
     *
     * @throws SubscriptionAlreadyExistsException When the store already has a subscription.
     * @throws PlanNotAvailableException When the plan has been retired.
     * @throws InvalidArgumentException When a trial has no end date, or an active subscription has one.
     */
    public function handle(Tenant $store, Plan $plan, SubscriptionStatus $status, ?CarbonImmutable $trialEndsAt = null): Subscription
    {
        $this->ensureValidStartingStatus($status, $trialEndsAt);
        $this->ensurePlanIsAvailable($plan);
        $this->ensureStoreHasNoSubscription($store);

        $subscription = Subscription::query()->create([
            'tenant_id' => $store->getTenantKey(),
            'plan_id' => $plan->id,
            'status' => $status,
            'trial_ends_at' => $trialEndsAt,
        ]);

        $this->features->forget((string) $store->getTenantKey());

        return $subscription;
    }

    private function ensureValidStartingStatus(SubscriptionStatus $status, ?CarbonImmutable $trialEndsAt): void
    {
        if ($status !== SubscriptionStatus::Trialing && $status !== SubscriptionStatus::Active) {
            throw new InvalidArgumentException("A subscription can only start as trialing or active, not {$status->value}.");
        }

        if (($status === SubscriptionStatus::Trialing) !== ($trialEndsAt !== null)) {
            throw new InvalidArgumentException('A trial needs an end date, and only a trial has one.');
        }
    }

    private function ensurePlanIsAvailable(Plan $plan): void
    {
        if (! $plan->is_active) {
            throw new PlanNotAvailableException($plan);
        }
    }

    private function ensureStoreHasNoSubscription(Tenant $store): void
    {
        if (Subscription::query()->where('tenant_id', $store->getTenantKey())->exists()) {
            throw new SubscriptionAlreadyExistsException;
        }
    }
}
