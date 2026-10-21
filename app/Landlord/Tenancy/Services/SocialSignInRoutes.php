<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Tenancy\Models\SocialSignInState;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreSocialSignInRoutes;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * The central side of customer social sign-in (Step 10b): which store each one-time state belongs to, so the relay page sends the provider's answer back to that store and nowhere else.
 */
final readonly class SocialSignInRoutes implements StoreSocialSignInRoutes
{
    public function open(string $stateHash, string $provider, CarbonImmutable $expiresAt): void
    {
        $store = tenant();

        if (! $store instanceof Tenant) {
            throw new LogicException('A social sign-in starts in a store.');
        }

        $route = new SocialSignInState;
        $route->forceFill([
            'state_hash' => $stateHash,
            'tenant_id' => $store->getTenantKey(),
            'provider' => $provider,
            'expires_at' => $expiresAt,
            'created_at' => CarbonImmutable::now(),
        ])->save();
    }
}
