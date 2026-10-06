<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNameTakenException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAuthority;
use App\Tenant\Identity\Services\StaffRoleNames;
use App\Tenant\Identity\Services\StaffRolePermissions;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Creates a role in the store, such as "Cashier", with the permissions it grants.
 */
final readonly class CreateStaffRole
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private StaffRoleNames $staffRoleNames,
        private StaffRolePermissions $staffRolePermissions,
    ) {}

    /**
     * @param  list<string>  $permissionNames  Names from the permission catalogue.
     *
     * @throws RoleNameTakenException When the name is already used, ignoring case.
     * @throws PermissionsExceedYourOwnException When the role would grant permissions the actor doesn't hold.
     */
    public function handle(StaffMember $actor, string $name, array $permissionNames): Role
    {
        $this->staffAuthority->ensureCanGrant($actor, $permissionNames);

        return Role::query()->getConnection()->transaction(function () use ($actor, $name, $permissionNames): Role {
            $this->staffRoleNames->ensureAvailable($name);

            try {
                $role = Role::query()->create(['name' => $name, 'guard_name' => StaffMember::GUARD]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new RoleNameTakenException(previous: $exception);
            }
            $this->staffRolePermissions->replace($role, $permissionNames);

            activity('team')
                ->causedBy($actor)
                ->performedOn($role)
                ->event('role_created')
                ->withProperties(['permissions' => $permissionNames])
                ->log("Created the role {$role->name}");

            return $role;
        });
    }
}
