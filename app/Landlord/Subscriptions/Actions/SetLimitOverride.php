<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Subscriptions\Data\LimitOverrideValue;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Features;
use Carbon\CarbonImmutable;

/**
 * Sets one store's usage limit instead of its plan's, optionally until a date, such as 500 products for a launch.
 *
 * Unlimited is only ever set on purpose (section 8). A lower limit never
 * deletes anything; it only stops the store creating more.
 */
final readonly class SetLimitOverride
{
    public function __construct(private Features $features) {}

    /**
     * @param  string  $limitKey  One of the keys in config/features.php.
     * @param  CarbonImmutable|null  $expiresAt  When the plan's limit applies again; null for no end date.
     */
    public function handle(PlatformAdmin $actor, Tenant $store, string $limitKey, LimitOverrideValue $value, ?CarbonImmutable $expiresAt, string $reason): TenantLimitOverride
    {
        $limitOverride = TenantLimitOverride::query()->updateOrCreate(
            ['tenant_id' => $store->id, 'limit_key' => $limitKey],
            ['limit_value' => $value->limit, 'is_unlimited' => $value->isUnlimited, 'expires_at' => $expiresAt, 'reason' => $reason],
        );

        activity('stores')
            ->causedBy($actor)
            ->performedOn($store)
            ->event('limit_override_set')
            ->withProperties([
                'limit' => $limitKey,
                'value' => $value->limit,
                'unlimited' => $value->isUnlimited,
                'expires_at' => $expiresAt?->toIso8601String(),
                'reason' => $reason,
            ])
            ->log("Set the {$limitKey} limit of the store {$store->name}");

        $this->features->forget($store->id);

        return $limitOverride;
    }
}
