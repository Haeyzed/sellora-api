<?php

declare(strict_types=1);

namespace App\Landlord\Identity;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Shared\Auth\Contracts\GrantablePermission;
use App\Shared\Auth\Contracts\GrantablePermissions;

/**
 * Every permission platform roles can grant: the cases of PlatformPermission.
 */
final class PlatformPermissionCatalogue implements GrantablePermissions
{
    /**
     * @return list<GrantablePermission>
     */
    public function permissions(): array
    {
        $permissions = PlatformPermission::cases();
        usort($permissions, static fn (PlatformPermission $first, PlatformPermission $second): int => strcmp($first->value, $second->value));

        return $permissions;
    }

    public function find(string $name): ?GrantablePermission
    {
        return PlatformPermission::tryFrom($name);
    }
}
