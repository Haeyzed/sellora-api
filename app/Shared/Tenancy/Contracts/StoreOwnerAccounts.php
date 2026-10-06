<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Creates a new store's owner account in the store's own database, while tenancy is initialized for that store.
 *
 * Implemented by Tenant\Identity, where staff accounts live, so the platform
 * side that sets stores up never writes to a store's database itself.
 */
interface StoreOwnerAccounts
{
    /**
     * Whether the store already has its owner, so setting it up again doesn't create a second one.
     */
    public function hasOwner(): bool;

    /**
     * Creates the owner's staff account with the Owner role.
     *
     * @param  string  $passwordHash  The hash of the password the owner chose when signing up; the password itself is never kept.
     */
    public function createOwner(string $name, string $email, string $passwordHash): void;
}
