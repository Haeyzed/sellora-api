<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Exceptions\PlatformPermissionsExceedYourOwnException;
use App\Landlord\Identity\Exceptions\PlatformRoleNameTakenException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformRoleNames;
use App\Landlord\Identity\Services\PlatformRolePermissions;
use App\Landlord\Identity\Services\PlatformTeamRules;
use App\Shared\Auth\Models\Role;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Creates a platform role, such as "Support", with the permissions it grants.
 */
final readonly class CreatePlatformRole
{
    public function __construct(
        private PlatformRoleNames $platformRoleNames,
        private PlatformRolePermissions $platformRolePermissions,
        private PlatformTeamRules $platformTeamRules,
    ) {}

    /**
     * @param  list<PlatformPermission>  $permissions
     *
     * @throws PlatformPermissionsExceedYourOwnException When the actor lacks one of the permissions.
     * @throws PlatformRoleNameTakenException When the name is already used, ignoring case.
     */
    public function handle(PlatformAdmin $actor, string $name, array $permissions): Role
    {
        $this->platformTeamRules->ensureCanGrant($actor, $permissions);

        return Role::query()->getConnection()->transaction(function () use ($actor, $name, $permissions): Role {
            $this->platformRoleNames->ensureAvailable($name);

            try {
                $role = Role::query()->create(['name' => $name, 'guard_name' => PlatformAdmin::GUARD]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new PlatformRoleNameTakenException(previous: $exception);
            }

            $this->platformRolePermissions->replace($role, $permissions);

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($role)
                ->event('platform_role_created')
                ->withProperties(['permissions' => array_map(static fn (PlatformPermission $permission): string => $permission->value, $permissions)])
                ->log("Created the platform role {$role->name}");

            return $role;
        });
    }
}
