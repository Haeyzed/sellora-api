<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Services;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Models\StaffMember;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets exactly which permissions a store role grants, with a field-level audit of what was added and removed.
 */
final readonly class StaffRolePermissions
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    /**
     * Replaces the role's permissions. Callers pass only names from the permission catalogue.
     *
     * @param  list<string>  $permissionNames
     */
    public function replace(Role $role, array $permissionNames): void
    {
        $permissionIds = array_map(
            static fn (string $permissionName): int => Permission::findOrCreate($permissionName, StaffMember::GUARD)->getKey(),
            array_values(array_unique($permissionNames)),
        );

        $role->auditSync('permissions', $permissionIds, columns: ['permissions.name']);
        $role->unsetRelation('permissions');
        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
