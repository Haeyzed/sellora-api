<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Exceptions\PlatformRoleNameTakenException;
use App\Landlord\Identity\Exceptions\PlatformRoleProtectedException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformRoleNames;
use App\Landlord\Identity\Services\PlatformRolePermissions;
use App\Shared\Auth\Models\Role;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Renames a platform role and sets what it grants. The change applies at once to everyone with the role.
 */
final readonly class UpdatePlatformRole
{
    public function __construct(
        private PlatformRoleNames $platformRoleNames,
        private PlatformRolePermissions $platformRolePermissions,
    ) {}

    /**
     * @param  list<PlatformPermission>  $permissions  The complete new set.
     *
     * @throws PlatformRoleProtectedException When it is the built-in Super Admin role.
     * @throws PlatformRoleNameTakenException When the new name is already used, ignoring case.
     */
    public function handle(PlatformAdmin $actor, Role $role, string $name, array $permissions): Role
    {
        if ($role->name === PlatformRole::SuperAdmin->value) {
            throw new PlatformRoleProtectedException;
        }

        return Role::query()->getConnection()->transaction(function () use ($actor, $role, $name, $permissions): Role {
            $this->platformRoleNames->ensureAvailable($name, $role);

            $previousName = $role->name;
            $previousPermissionNames = PlatformRolePermissions::namesOf($role);

            try {
                $role->update(['name' => $name]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new PlatformRoleNameTakenException(previous: $exception);
            }

            $this->platformRolePermissions->replace($role, $permissions);
            $permissionNames = array_map(static fn (PlatformPermission $permission): string => $permission->value, $permissions);

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($role)
                ->event('platform_role_updated')
                ->withProperties([
                    'previous_name' => $previousName,
                    'added_permissions' => array_values(array_diff($permissionNames, $previousPermissionNames)),
                    'removed_permissions' => array_values(array_diff($previousPermissionNames, $permissionNames)),
                ])
                ->log("Updated the platform role {$role->name}");

            return $role;
        });
    }
}
