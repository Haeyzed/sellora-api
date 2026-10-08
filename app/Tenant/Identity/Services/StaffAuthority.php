<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Services;

use App\Shared\Auth\EffectivePermissions;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Exceptions\CannotManageOwnAccountException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Exceptions\StoreOwnerProtectedException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffPermissionCatalogue;
use Spatie\Permission\Models\Permission;

/**
 * The rules that stop staff from giving themselves or others more power than they have.
 *
 * - Nobody can grant a permission they don't hold, through a role or an
 *   invitation, so a staff member who may edit roles can't create an
 *   all-powerful role and use it.
 * - Nobody can manage a colleague who holds permissions they don't.
 * - Nobody changes their own roles or deactivates themselves.
 * - The owner holds every permission, and is only changed by an ownership
 *   transfer, never here.
 */
final readonly class StaffAuthority
{
    public function __construct(private StaffPermissionCatalogue $permissionCatalogue) {}

    public function isOwner(StaffMember $staffMember): bool
    {
        return $staffMember->hasRole(StaffRole::Owner->value);
    }

    /**
     * Every permission a staff member holds through their roles. The owner holds the whole catalogue.
     *
     * @return list<string>
     */
    public function permissionsOf(StaffMember $staffMember): array
    {
        if ($this->isOwner($staffMember)) {
            return $this->permissionCatalogue->all();
        }

        return array_values($staffMember->getAllPermissions()->map(static fn (Permission $permission): string => $permission->name)->unique()->all());
    }

    /**
     * Every permission a staff member holds and where each comes from: their roles, or given directly. The owner holds the whole catalogue through the Owner role.
     */
    public function effectivePermissionsOf(StaffMember $staffMember): EffectivePermissions
    {
        if ($this->isOwner($staffMember)) {
            return EffectivePermissions::everythingThrough(StaffRole::Owner->value, $this->permissionCatalogue->all());
        }

        $roles = Role::query()
            ->with('permissions')
            ->where('guard_name', StaffMember::GUARD)
            ->whereIn('name', $staffMember->getRoleNames())
            ->get();
        $permissionsByRole = [];

        foreach ($roles as $role) {
            $permissionsByRole[$role->name] = $this->permissionNamesOf($role);
        }

        return EffectivePermissions::from($permissionsByRole, array_values($staffMember->permissions()->pluck('name')->all()));
    }

    /**
     * @param  iterable<string>  $permissions
     *
     * @throws PermissionsExceedYourOwnException When the actor lacks any of the permissions.
     */
    public function ensureCanGrant(StaffMember $actor, iterable $permissions): void
    {
        if ($this->isOwner($actor)) {
            return;
        }

        $held = array_flip($this->permissionsOf($actor));

        foreach ($permissions as $permission) {
            if (! isset($held[$permission])) {
                throw new PermissionsExceedYourOwnException;
            }
        }
    }

    /**
     * Checks roles about to be given to someone: never Owner, and only roles whose permissions the actor holds.
     *
     * @param  iterable<Role>  $roles
     *
     * @throws RoleNotAssignableException When one of them is the Owner role.
     * @throws PermissionsExceedYourOwnException When the roles allow more than the actor may.
     */
    public function ensureCanGiveRoles(StaffMember $actor, iterable $roles): void
    {
        foreach ($roles as $role) {
            if ($role->name === StaffRole::Owner->value) {
                throw new RoleNotAssignableException;
            }

            $this->ensureCanGrant($actor, $this->permissionNamesOf($role));
        }
    }

    /**
     * Checks that the actor may change a colleague's roles or deactivate them.
     *
     * @throws CannotManageOwnAccountException When it is the actor's own account.
     * @throws StoreOwnerProtectedException When the colleague is the owner.
     * @throws PermissionsExceedYourOwnException When the colleague holds permissions the actor doesn't.
     */
    public function ensureCanManage(StaffMember $actor, StaffMember $colleague): void
    {
        if ($actor->is($colleague)) {
            throw new CannotManageOwnAccountException;
        }

        if ($this->isOwner($colleague)) {
            throw new StoreOwnerProtectedException;
        }

        $this->ensureCanGrant($actor, $this->permissionsOf($colleague));
    }

    /**
     * @return list<string>
     */
    public function permissionNamesOf(Role $role): array
    {
        return array_values($role->permissions->map(static fn (Permission $permission): string => $permission->name)->all());
    }
}
