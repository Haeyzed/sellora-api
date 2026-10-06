<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Tenancy\Contracts\StoreSessions;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Signs out every account of the current store (staff, customers and drivers), used when the store closes or is restored.
 *
 * It deletes every token in the store's own database rather than listing
 * account types, so a kind of account added later can't be left signed in.
 */
final readonly class StoreSignOut implements StoreSessions
{
    public function endAll(): void
    {
        PersonalAccessToken::query()->delete();
    }
}
