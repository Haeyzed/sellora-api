<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use Carbon\CarbonImmutable;

/**
 * Tells the central relay page where to send a provider's answer to a customer social sign-in started in the current store (Step 10b).
 *
 * Implemented by Landlord, which keeps only the routing: the state's hash,
 * the store, the provider and when it expires. Nothing about the customer
 * is ever passed here.
 */
interface StoreSocialSignInRoutes
{
    /**
     * @param  string  $stateHash  SHA-256 of the one-time state; the state itself never leaves the store.
     */
    public function open(string $stateHash, string $provider, CarbonImmutable $expiresAt): void;
}
