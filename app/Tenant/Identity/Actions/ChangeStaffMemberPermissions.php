<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Tenant\Identity\Exceptions\CannotManageOwnAccountException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\StoreOwnerProtectedException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAuthority;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Replaces the permissions a staff member holds directly, on top of their roles (section 10). Applies to their very next request.
 *
 * The same rules as roles: nobody changes their own permissions or the
 * owner's, nobody manages a colleague who holds permissions they don't, and
 * nobody grants a permission they don't hold. Audited and logged.
 */
final readonly class ChangeStaffMemberPermissions
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @param  list<string>  $permissionNames  The complete new set of direct permissions, all from the permission catalogue.
     *
     * @throws CannotManageOwnAccountException When the actor changes their own permissions.
     * @throws StoreOwnerProtectedException When the staff member is the owner.
     * @throws PermissionsExceedYourOwnException When the staff member or the new permissions go beyond what the actor holds.
     */
    public function handle(StaffMember $actor, StaffMember $staffMember, array $permissionNames): StaffMember
    {
        $this->staffAuthority->ensureCanManage($actor, $staffMember);
        $this->staffAuthority->ensureCanGrant($actor, $permissionNames);

        return StaffMember::query()->getConnection()->transaction(function () use ($actor, $staffMember, $permissionNames): StaffMember {
            $previous = $this->directPermissionNames($staffMember);
            $permissionIds = array_map(
                static fn (string $name): int => Permission::findByName($name, StaffMember::GUARD)->getKey(),
                array_values(array_unique($permissionNames)),
            );

            $staffMember->auditSync('permissions', $permissionIds, columns: ['permissions.name']);
            $staffMember->unsetRelation('permissions');
            $this->permissionRegistrar->forgetCachedPermissions();

            activity('team')
                ->causedBy($actor)
                ->performedOn($staffMember)
                ->event('staff_permissions_changed')
                ->withProperties(['previous_permissions' => $previous, 'permissions' => $this->directPermissionNames($staffMember)])
                ->log("Changed the direct permissions of {$staffMember->name}");

            return $staffMember;
        });
    }

    /**
     * @return list<string>
     */
    private function directPermissionNames(StaffMember $staffMember): array
    {
        return array_values($staffMember->permissions()->orderBy('name')->pluck('name')->all());
    }
}
