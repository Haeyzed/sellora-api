<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Exceptions\CannotManageOwnAccountException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Exceptions\StoreOwnerProtectedException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAuthority;

/**
 * Replaces a staff member's roles. Applies to their very next request.
 */
final readonly class ChangeStaffMemberRoles
{
    public function __construct(private StaffAuthority $staffAuthority) {}

    /**
     * @param  list<Role>  $roles  The complete new set of roles.
     *
     * @throws CannotManageOwnAccountException When the actor changes their own roles.
     * @throws StoreOwnerProtectedException When the staff member is the owner.
     * @throws PermissionsExceedYourOwnException When the staff member or the new roles have permissions the actor doesn't hold.
     * @throws RoleNotAssignableException When one of the roles is Owner.
     */
    public function handle(StaffMember $actor, StaffMember $staffMember, array $roles): StaffMember
    {
        $this->staffAuthority->ensureCanManage($actor, $staffMember);
        $this->staffAuthority->ensureCanGiveRoles($actor, $roles);

        return StaffMember::query()->getConnection()->transaction(static function () use ($actor, $staffMember, $roles): StaffMember {
            $previousRoleNames = array_values($staffMember->getRoleNames()->all());
            $staffMember->syncRoles($roles);
            $newRoleNames = array_map(static fn (Role $role): string => $role->name, $roles);

            activity('team')
                ->causedBy($actor)
                ->performedOn($staffMember)
                ->event('staff_roles_changed')
                ->withProperties(['previous_roles' => $previousRoleNames, 'roles' => $newRoleNames])
                ->log("Changed the roles of {$staffMember->name}");

            return $staffMember->load('roles');
        });
    }
}
