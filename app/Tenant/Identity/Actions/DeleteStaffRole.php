<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleInUseException;
use App\Tenant\Identity\Exceptions\RoleProtectedException;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAuthority;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deletes a store role that nobody has any more.
 */
final readonly class DeleteStaffRole
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @throws RoleProtectedException When it is the built-in Owner role.
     * @throws PermissionsExceedYourOwnException When the role grants permissions the actor doesn't hold.
     * @throws RoleInUseException When staff members or pending invitations still have it.
     */
    public function handle(StaffMember $actor, Role $role): void
    {
        if ($role->name === StaffRole::Owner->value) {
            throw new RoleProtectedException;
        }

        $this->staffAuthority->ensureCanGrant($actor, $this->staffAuthority->permissionNamesOf($role));

        Role::query()->getConnection()->transaction(function () use ($actor, $role): void {
            $isInUse = StaffMember::query()->role($role)->exists()
                || StaffInvitation::query()->pending()->whereHas('roles', static fn (Builder $query) => $query->whereKey($role->getKey()))->exists();

            if ($isInUse) {
                throw new RoleInUseException;
            }

            activity('team')
                ->causedBy($actor)
                ->event('role_deleted')
                ->withProperties(['name' => $role->name, 'permissions' => $this->staffAuthority->permissionNamesOf($role)])
                ->log("Deleted the role {$role->name}");

            $role->delete();
            $this->permissionRegistrar->forgetCachedPermissions();
        });
    }
}
