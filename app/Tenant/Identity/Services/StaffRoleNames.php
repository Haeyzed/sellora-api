<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Services;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Exceptions\RoleNameTakenException;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Keeps store role names unique ignoring case, so "Manager" and "manager" can't both exist, and keeps built-in names reserved.
 *
 * This check gives a clear error up front; a unique index on lower(name)
 * holds the rule even when two requests race each other.
 */
final readonly class StaffRoleNames
{
    /**
     * @param  Role|null  $renaming  The role being renamed, which may keep its own name.
     *
     * @throws RoleNameTakenException When another role has the name, or it is a built-in role's name.
     */
    public function ensureAvailable(string $name, ?Role $renaming = null): void
    {
        $lowerCaseName = mb_strtolower($name);

        foreach (StaffRole::cases() as $builtInRole) {
            if ($lowerCaseName === $builtInRole->value) {
                throw new RoleNameTakenException;
            }
        }

        $taken = Role::query()
            ->where('guard_name', StaffMember::GUARD)
            ->whereRaw('lower(name) = ?', [$lowerCaseName])
            ->when($renaming !== null, static fn ($query) => $query->whereKeyNot($renaming?->getKey()))
            ->exists();

        if ($taken) {
            throw new RoleNameTakenException;
        }
    }
}
