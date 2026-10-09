<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Exceptions\LastSuperAdminException;
use App\Landlord\Identity\Exceptions\PlatformPermissionsExceedYourOwnException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformTeamRules;
use App\Shared\Auth\Models\Role;

/**
 * Replaces a platform admin's roles. Applies to their very next request.
 */
final readonly class ChangePlatformAdminRoles
{
    public function __construct(private PlatformTeamRules $platformTeamRules) {}

    /**
     * @param  list<Role>  $roles  The complete new set of platform roles.
     *
     * @throws CannotManageOwnPlatformAccountException When the actor changes their own roles.
     * @throws LastSuperAdminException When it would take the Super Admin role from the last active super admin.
     * @throws PlatformPermissionsExceedYourOwnException When the roles allow more than the actor may.
     */
    public function handle(PlatformAdmin $actor, PlatformAdmin $platformAdmin, array $roles): PlatformAdmin
    {
        $this->platformTeamRules->ensureNotSelf($actor, $platformAdmin);
        $this->platformTeamRules->ensureCanGiveRoles($actor, $roles);

        return PlatformAdmin::query()->getConnection()->transaction(function () use ($actor, $platformAdmin, $roles): PlatformAdmin {
            $keepsSuperAdmin = array_any($roles, static fn (Role $role): bool => $role->name === PlatformRole::SuperAdmin->value);

            if (! $keepsSuperAdmin) {
                $this->platformTeamRules->ensureAnotherSuperAdminRemains($platformAdmin);
            }

            $previousRoleNames = array_values($platformAdmin->getRoleNames()->all());
            $platformAdmin->syncRoles($roles);

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($platformAdmin)
                ->event('platform_admin_roles_changed')
                ->withProperties(['previous_roles' => $previousRoleNames, 'roles' => array_map(static fn (Role $role): string => $role->name, $roles)])
                ->log("Changed the roles of {$platformAdmin->name}");

            return $platformAdmin->load('roles');
        });
    }
}
