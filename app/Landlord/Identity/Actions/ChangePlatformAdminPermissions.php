<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Exceptions\PlatformPermissionsExceedYourOwnException;
use App\Landlord\Identity\Exceptions\SuperAdminProtectedException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformTeamRules;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Replaces the permissions a platform admin holds directly, on top of their roles (section 10). Applies to their very next request.
 *
 * Nobody grants a permission they don't hold (today only super admins manage
 * the team, and they hold every permission). Nobody changes their own
 * permissions, and super admins are protected. Audited and logged.
 */
final readonly class ChangePlatformAdminPermissions
{
    public function __construct(
        private PlatformTeamRules $platformTeamRules,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @param  list<PlatformPermission>  $permissions  The complete new set of direct permissions.
     *
     * @throws CannotManageOwnPlatformAccountException When the actor changes their own permissions.
     * @throws SuperAdminProtectedException When the admin is a super admin.
     * @throws PlatformPermissionsExceedYourOwnException When the actor lacks one of the permissions.
     */
    public function handle(PlatformAdmin $actor, PlatformAdmin $platformAdmin, array $permissions): PlatformAdmin
    {
        $this->platformTeamRules->ensureNotSelf($actor, $platformAdmin);

        if ($platformAdmin->isSuperAdmin()) {
            throw new SuperAdminProtectedException;
        }

        $this->platformTeamRules->ensureCanGrant($actor, $permissions);

        return PlatformAdmin::query()->getConnection()->transaction(function () use ($actor, $platformAdmin, $permissions): PlatformAdmin {
            $previous = $this->directPermissionNames($platformAdmin);
            $permissionIds = array_map(
                static fn (PlatformPermission $permission): int => Permission::findByName($permission->value, PlatformAdmin::GUARD)->getKey(),
                array_values(array_unique($permissions, SORT_REGULAR)),
            );

            $platformAdmin->auditSync('permissions', $permissionIds, columns: ['permissions.name']);
            $platformAdmin->unsetRelation('permissions');
            $this->permissionRegistrar->forgetCachedPermissions();

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($platformAdmin)
                ->event('platform_admin_permissions_changed')
                ->withProperties(['previous_permissions' => $previous, 'permissions' => $this->directPermissionNames($platformAdmin)])
                ->log("Changed the direct permissions of {$platformAdmin->name}");

            return $platformAdmin;
        });
    }

    /**
     * @return list<string>
     */
    private function directPermissionNames(PlatformAdmin $platformAdmin): array
    {
        return array_values($platformAdmin->permissions()->orderBy('name')->pluck('name')->all());
    }
}
