<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNameTakenException;
use App\Tenant\Identity\Exceptions\RoleProtectedException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAuthority;
use App\Tenant\Identity\Services\StaffRoleNames;
use App\Tenant\Identity\Services\StaffRolePermissions;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Renames a store role and sets what it grants. The change applies at once to everyone with the role.
 */
final readonly class UpdateStaffRole
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private StaffRoleNames $staffRoleNames,
        private StaffRolePermissions $staffRolePermissions,
    ) {}

    /**
     * @param  list<string>  $permissionNames  The complete new set, from the permission catalogue.
     *
     * @throws RoleProtectedException When it is the built-in Owner role.
     * @throws PermissionsExceedYourOwnException When the role grants, or would grant, permissions the actor doesn't hold.
     * @throws RoleNameTakenException When the new name is already used, ignoring case.
     */
    public function handle(StaffMember $actor, Role $role, string $name, array $permissionNames): Role
    {
        if ($role->name === StaffRole::Owner->value) {
            throw new RoleProtectedException;
        }

        $previousPermissionNames = $this->staffAuthority->permissionNamesOf($role);
        $this->staffAuthority->ensureCanGrant($actor, [...$previousPermissionNames, ...$permissionNames]);

        return Role::query()->getConnection()->transaction(function () use ($actor, $role, $name, $permissionNames, $previousPermissionNames): Role {
            $this->staffRoleNames->ensureAvailable($name, $role);

            $previousName = $role->name;
            try {
                $role->update(['name' => $name]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new RoleNameTakenException(previous: $exception);
            }
            $this->staffRolePermissions->replace($role, $permissionNames);

            activity('team')
                ->causedBy($actor)
                ->performedOn($role)
                ->event('role_updated')
                ->withProperties([
                    'previous_name' => $previousName,
                    'added_permissions' => array_values(array_diff($permissionNames, $previousPermissionNames)),
                    'removed_permissions' => array_values(array_diff($previousPermissionNames, $permissionNames)),
                ])
                ->log("Updated the role {$role->name}");

            return $role;
        });
    }
}
