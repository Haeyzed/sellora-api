<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Subscriptions\Exceptions\FeatureGrantNotActiveException;
use App\Landlord\Subscriptions\Models\FeatureGrant;
use App\Shared\Features\Features;
use Carbon\CarbonImmutable;

/**
 * Ends a feature grant now. Unless the store's plan includes the feature, it becomes locked: existing data stays readable, nothing new can be added.
 */
final readonly class RevokeFeatureGrant
{
    public function __construct(private Features $features) {}

    /**
     * @throws FeatureGrantNotActiveException When it already ended.
     */
    public function handle(PlatformAdmin $actor, FeatureGrant $featureGrant): FeatureGrant
    {
        $featureGrant = FeatureGrant::query()->getConnection()->transaction(static function () use ($actor, $featureGrant): FeatureGrant {
            $locked = FeatureGrant::query()->whereKey($featureGrant->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new FeatureGrantNotActiveException;
            }

            $locked->forceFill(['revoked_at' => CarbonImmutable::now()]);
            $locked->revokedBy()->associate($actor);
            $locked->save();

            activity('stores')
                ->causedBy($actor)
                ->performedOn($locked->tenant)
                ->event('feature_grant_revoked')
                ->withProperties(['feature' => $locked->feature_key])
                ->log("Revoked {$locked->feature_key} from the store {$locked->tenant->name}");

            return $locked;
        });

        $this->features->forget($featureGrant->tenant_id);

        return $featureGrant;
    }
}
