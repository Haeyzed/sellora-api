<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Auth\Contracts\GrantablePermission;
use App\Shared\Auth\Contracts\GrantablePermissions;

/**
 * The one list of every permission a store's roles can grant, filled by each domain and module from its own permission enum.
 *
 * Roles can only be given permissions from this list, so a typo or a
 * permission from removed code can never be granted. Permissions are created
 * in a store's database the first time a role is given them.
 */
final class StaffPermissionCatalogue implements GrantablePermissions
{
    /**
     * @var array<string, GrantablePermission>
     */
    private array $permissions = [];

    /**
     * Adds every case of a permission enum, such as StaffPermission.
     *
     * @param  class-string<GrantablePermission>  $permissionEnum
     */
    public function register(string $permissionEnum): void
    {
        foreach ($permissionEnum::cases() as $permission) {
            $this->permissions[(string) $permission->value] = $permission;
        }
    }

    /**
     * Every permission name, sorted.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $names = array_keys($this->permissions);
        sort($names);

        return $names;
    }

    /**
     * @return list<GrantablePermission>
     */
    public function permissions(): array
    {
        return array_map(fn (string $name): GrantablePermission => $this->permissions[$name], $this->all());
    }

    public function find(string $name): ?GrantablePermission
    {
        return $this->permissions[$name] ?? null;
    }

    public function has(string $permission): bool
    {
        return isset($this->permissions[$permission]);
    }
}
