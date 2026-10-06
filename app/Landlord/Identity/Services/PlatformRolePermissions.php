<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sets exactly which permissions a platform role grants, with a field-level audit of what was added and removed.
 */
final readonly class PlatformRolePermissions
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    /**
     * @param  list<PlatformPermission>  $permissions
     */
    public function replace(Role $role, array $permissions): void
    {
        $permissionIds = array_map(
            static fn (PlatformPermission $permission): int => Permission::findOrCreate($permission->value, PlatformAdmin::GUARD)->getKey(),
            array_values(array_unique($permissions, SORT_REGULAR)),
        );

        $role->auditSync('permissions', $permissionIds, columns: ['permissions.name']);
        $role->unsetRelation('permissions');
        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    public static function namesOf(Role $role): array
    {
        return array_values($role->permissions()->pluck('name')->sort()->values()->all());
    }
}
