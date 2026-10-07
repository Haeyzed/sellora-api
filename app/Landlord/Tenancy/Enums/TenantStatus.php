<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Enums;

/**
 * Where a store is in its life: being set up, open, suspended by the platform, failed to set up, closed, or purged.
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
     * Suspended by a platform admin, for example for fraud. Its features are suspended, as with an unpaid subscription:
     * staff can still sign in, customers see a friendly unavailable response, and what was already started can be finished.
     */
    case Suspended = 'suspended';

    /**
     * Closed by its owner or a platform admin. It serves no requests and everyone was signed out, but its database is
     * kept (and migrated) until its purge date, so a platform admin can still restore it.
     */
    case Closed = 'closed';

    /**
     * Its data is being deleted for good. Can no longer be restored; a purge that stopped halfway carries on from here.
     */
    case Purging = 'purging';

    /**
     * Its data is gone: no database, files, domains or owner details. The row stays as a record, with its
     * subscription history and the legal acceptances that were the contract.
     */
    case Purged = 'purged';

    /**
     * Whether the store's domains serve requests.
     */
    public function servesRequests(): bool
    {
        return $this === self::Active || $this === self::Suspended;
    }

    /**
     * The statuses a store can be closed from.
     *
     * @return list<self>
     */
    public static function closable(): array
    {
        return [self::Active, self::Suspended, self::ProvisioningFailed];
    }

    /**
     * The statuses of stores that may have no database (not yet, or no longer), which commands run across every store skip.
     *
     * @return list<self>
     */
    public static function withoutDatabase(): array
    {
        return [self::Provisioning, self::ProvisioningFailed, self::Purging, self::Purged];
    }
}
