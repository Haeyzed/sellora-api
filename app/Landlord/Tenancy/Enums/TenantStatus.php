<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Enums;

/**
 * Where a store is in its life: being set up, ready, or failed to set up.
 */
enum TenantStatus: string
{
    /** Registered; its database is being created. It serves no requests yet. */
    case Provisioning = 'provisioning';

    /** Setting up failed after every retry. A platform admin has to look at it. */
    case ProvisioningFailed = 'provisioning_failed';

    /** Open for business. */
    case Active = 'active';

    /**
     * Whether the store's domains serve requests.
     */
    public function servesRequests(): bool
    {
        return $this === self::Active;
    }
}
