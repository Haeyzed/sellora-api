<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use BackedEnum;

/**
 * The one list of every permission a store's roles can grant, filled by each domain and module from its own permission enum.
 *
 * Roles can only be given permissions from this list, so a typo or a
 * permission from removed code can never be granted. Permissions are created
 * in a store's database the first time a role is given them.
 */
final class StaffPermissionCatalogue
{
    /**
     * @var array<string, true>
     */
    private array $permissions = [];

    /**
     * Adds every case of a permission enum, such as StaffPermission.
     *
     * @param  class-string<BackedEnum>  $permissionEnum
     */
    public function register(string $permissionEnum): void
    {
        foreach ($permissionEnum::cases() as $permission) {
            $this->permissions[(string) $permission->value] = true;
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

    public function has(string $permission): bool
    {
        return isset($this->permissions[$permission]);
    }
}
