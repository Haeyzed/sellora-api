<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Tenancy\Contracts\StoreOwnerAccounts;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;

/**
 * A store's owner is a staff member with the Owner role, created when the store is set up.
 *
 * The owner isn't counted against the plan's staff limit here: a store
 * always has its owner, whatever its plan.
 */
final readonly class StaffStoreOwnerAccounts implements StoreOwnerAccounts
{
    public function hasOwner(): bool
    {
        return StaffMember::role(StaffRole::Owner->value, StaffMember::GUARD)->exists();
    }

    public function createOwner(string $name, string $email, string $passwordHash): void
    {
        StaffMember::query()->getConnection()->transaction(static function () use ($name, $email, $passwordHash): void {
            // Already a hash: the "hashed" cast keeps a value that is one, instead of hashing it again.
            $owner = StaffMember::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $passwordHash,
                'is_active' => true,
            ]);
            $owner->assignRole(StaffRole::Owner->value);

            activity('team')
                ->performedOn($owner)
                ->event('store_owner_created')
                ->log("{$owner->name} opened the store");
        });
    }
}
