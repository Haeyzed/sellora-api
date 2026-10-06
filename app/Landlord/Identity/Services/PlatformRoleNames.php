<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Exceptions\PlatformRoleNameTakenException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Models\Role;

/**
 * Keeps platform role names unique ignoring case, and keeps built-in names reserved.
 *
 * This check gives a clear error up front; the unique index on lower(name)
 * holds the rule even when two requests race each other.
 */
final readonly class PlatformRoleNames
{
    /**
     * @param  Role|null  $renaming  The role being renamed, which may keep its own name.
     *
     * @throws PlatformRoleNameTakenException When another role has the name, or it is a built-in role's name.
     */
    public function ensureAvailable(string $name, ?Role $renaming = null): void
    {
        $lowerCaseName = mb_strtolower($name);

        foreach (PlatformRole::cases() as $builtInRole) {
            if ($lowerCaseName === $builtInRole->value) {
                throw new PlatformRoleNameTakenException;
            }
        }

        $taken = Role::query()
            ->where('guard_name', PlatformAdmin::GUARD)
            ->whereRaw('lower(name) = ?', [$lowerCaseName])
            ->when($renaming !== null, static fn ($query) => $query->whereKeyNot($renaming?->getKey()))
            ->exists();

        if ($taken) {
            throw new PlatformRoleNameTakenException;
        }
    }
}
