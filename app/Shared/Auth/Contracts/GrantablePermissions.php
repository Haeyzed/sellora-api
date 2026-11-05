<?php

declare(strict_types=1);

namespace App\Shared\Auth\Contracts;

/**
 * Every permission one kind of account's roles can grant, so shared code can describe a permission by its name without knowing which side defined it.
 */
interface GrantablePermissions
{
    /**
     * Every permission, sorted by name.
     *
     * @return list<GrantablePermission>
     */
    public function permissions(): array;

    /**
     * The permission with this name, or null for a name no code defines (such as one from removed code still held by a role).
     */
    public function find(string $name): ?GrantablePermission;
}
