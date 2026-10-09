<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Exceptions\LastSuperAdminException;
use App\Landlord\Identity\Exceptions\PlatformPermissionsExceedYourOwnException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Models\Role;
use LogicException;

/**
 * The rules every change to the platform team follows: nobody changes their own account this way, nobody grants a permission they don't hold, and the platform always keeps an active super admin.
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
     * Checks the actor holds every permission they are about to grant, through a role or directly.
     *
     * Today only super admins manage the team and they hold every permission,
     * so this never refuses them; it keeps the rule true if team management is
     * ever opened to other admins.
     *
     * @param  iterable<PlatformPermission|string>  $permissions
     *
     * @throws PlatformPermissionsExceedYourOwnException When the actor lacks any of them.
     */
    public function ensureCanGrant(PlatformAdmin $actor, iterable $permissions): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $held = array_flip(array_values($actor->getAllPermissions()->pluck('name')->all()));

        foreach ($permissions as $permission) {
            if (! isset($held[$permission instanceof PlatformPermission ? $permission->value : $permission])) {
                throw new PlatformPermissionsExceedYourOwnException;
            }
        }
    }

    /**
     * Checks roles about to be given to someone: only roles whose permissions the actor holds, and the Super Admin role only by a super admin.
     *
     * @param  iterable<Role>  $roles
     *
     * @throws PlatformPermissionsExceedYourOwnException When the roles allow more than the actor may.
     */
    public function ensureCanGiveRoles(PlatformAdmin $actor, iterable $roles): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        foreach ($roles as $role) {
            if ($role->name === PlatformRole::SuperAdmin->value) {
                throw new PlatformPermissionsExceedYourOwnException;
            }

            $this->ensureCanGrant($actor, PlatformRolePermissions::namesOf($role));
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
