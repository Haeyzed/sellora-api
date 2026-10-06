<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Exceptions\LastSuperAdminException;
use App\Landlord\Identity\Models\PlatformAdmin;
use LogicException;

/**
 * The rules every change to the platform team follows: nobody changes their own account this way, and the platform always keeps an active super admin.
 */
final readonly class PlatformTeamRules
{
    /**
     * @throws CannotManageOwnPlatformAccountException When the actor is the admin being changed.
     */
    public function ensureNotSelf(PlatformAdmin $actor, PlatformAdmin $platformAdmin): void
    {
        if ($actor->is($platformAdmin)) {
            throw new CannotManageOwnPlatformAccountException;
        }
    }

    /**
     * Checks another active super admin remains if this admin stops being one, holding a lock until the caller's transaction ends, so two super admins can't remove each other at once.
     *
     * @throws LastSuperAdminException When this admin is the only active super admin.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureAnotherSuperAdminRemains(PlatformAdmin $platformAdmin): void
    {
        $connection = PlatformAdmin::query()->getConnection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('Super admins must be counted inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', ['platform-super-admins']);

        if (! $platformAdmin->is_active || ! $platformAdmin->isSuperAdmin()) {
            return;
        }

        $otherActiveSuperAdmins = PlatformAdmin::role(PlatformRole::SuperAdmin->value, PlatformAdmin::GUARD)
            ->where('is_active', true)
            ->whereKeyNot($platformAdmin->getKey())
            ->count();

        if ($otherActiveSuperAdmins === 0) {
            throw new LastSuperAdminException;
        }
    }
}
