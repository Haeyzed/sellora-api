<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\EffectivePermissions;
use App\Shared\Auth\Models\Role;

/**
 * What a platform admin may do and why: each permission with the roles that give it, or given directly. A super admin holds every permission through the Super Admin role.
 */
final readonly class PlatformAdminPermissions
{
    public function effectivePermissionsOf(PlatformAdmin $platformAdmin): EffectivePermissions
    {
        if ($platformAdmin->isSuperAdmin()) {
            return EffectivePermissions::everythingThrough(
                PlatformRole::SuperAdmin->value,
                array_map(static fn (PlatformPermission $permission): string => $permission->value, PlatformPermission::cases()),
            );
        }

        $roles = Role::query()
            ->with('permissions')
            ->where('guard_name', PlatformAdmin::GUARD)
            ->whereIn('name', $platformAdmin->getRoleNames())
            ->get();
        $permissionsByRole = [];

        foreach ($roles as $role) {
            $permissionsByRole[$role->name] = PlatformRolePermissions::namesOf($role);
        }

        return EffectivePermissions::from($permissionsByRole, array_values($platformAdmin->permissions()->pluck('name')->all()));
    }
}
