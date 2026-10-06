<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Exceptions\PlatformRoleInUseException;
use App\Landlord\Identity\Exceptions\PlatformRoleProtectedException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Identity\Services\PlatformRolePermissions;
use App\Shared\Auth\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deletes a platform role that nobody has any more.
 */
final readonly class DeletePlatformRole
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    /**
     * @throws PlatformRoleProtectedException When it is the built-in Super Admin role.
     * @throws PlatformRoleInUseException When platform admins or pending invitations still have it.
     */
    public function handle(PlatformAdmin $actor, Role $role): void
    {
        if ($role->name === PlatformRole::SuperAdmin->value) {
            throw new PlatformRoleProtectedException;
        }

        Role::query()->getConnection()->transaction(function () use ($actor, $role): void {
            $isInUse = PlatformAdmin::query()->role($role)->exists()
                || PlatformAdminInvitation::query()->pending()->whereHas('roles', static fn (Builder $query) => $query->whereKey($role->getKey()))->exists();

            if ($isInUse) {
                throw new PlatformRoleInUseException;
            }

            activity('platform_team')
                ->causedBy($actor)
                ->event('platform_role_deleted')
                ->withProperties(['name' => $role->name, 'permissions' => PlatformRolePermissions::namesOf($role)])
                ->log("Deleted the platform role {$role->name}");

            $role->delete();
            $this->permissionRegistrar->forgetCachedPermissions();
        });
    }
}
