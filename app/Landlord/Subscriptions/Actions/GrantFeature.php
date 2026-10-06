<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Subscriptions\Exceptions\FeatureAlreadyGrantedException;
use App\Landlord\Subscriptions\Models\FeatureGrant;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Features;
use Carbon\CarbonImmutable;

/**
 * Gives one store a module or integration outside its plan, optionally until a date, such as "Loyalty free for 30 days".
 *
 * Plans are never edited for one store. When the grant ends, the feature is
 * locked like after a downgrade, so the store's data stays readable.
 */
final readonly class GrantFeature
{
    public function __construct(private Features $features) {}

    /**
     * @param  string  $featureKey  An installed module or integration.
     * @param  CarbonImmutable|null  $expiresAt  When it ends; null for no end date.
     *
     * @throws FeatureAlreadyGrantedException When the store already has an active grant for it.
     */
    public function handle(PlatformAdmin $actor, Tenant $store, string $featureKey, string $reason, ?CarbonImmutable $expiresAt): FeatureGrant
    {
        $featureGrant = FeatureGrant::query()->getConnection()->transaction(static function () use ($actor, $store, $featureKey, $reason, $expiresAt): FeatureGrant {
            // Held until commit, so two grants for the same feature can't both pass the check.
            FeatureGrant::query()->getConnection()->select('select pg_advisory_xact_lock(hashtext(?))', ["feature-grant:{$store->id}:{$featureKey}"]);

            if (FeatureGrant::query()->active()->where('tenant_id', $store->id)->where('feature_key', $featureKey)->exists()) {
                throw new FeatureAlreadyGrantedException;
            }

            $featureGrant = new FeatureGrant([
                'tenant_id' => $store->id,
                'feature_key' => $featureKey,
                'reason' => $reason,
                'expires_at' => $expiresAt,
            ]);
            $featureGrant->grantedBy()->associate($actor);
            $featureGrant->save();

            activity('stores')
                ->causedBy($actor)
                ->performedOn($store)
                ->event('feature_granted')
                ->withProperties(['feature' => $featureKey, 'expires_at' => $expiresAt?->toIso8601String(), 'reason' => $reason])
                ->log("Granted {$featureKey} to the store {$store->name}");

            return $featureGrant;
        });

        $this->features->forget($store->id);

        return $featureGrant;
    }
}
