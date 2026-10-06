<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Tenancy\Exceptions\SubdomainTakenException;
use App\Landlord\Tenancy\Models\Domain;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Models\Tenant;
use LogicException;

/**
 * Whether a subdomain on the platform domain is free for a new store.
 *
 * A sign-up holds its subdomain while it waits for its code, so someone else
 * can't take it in between. The unique index on domains settles the race if
 * two sign-ups are verified at the same moment.
 */
final readonly class StoreSubdomains
{
    /**
     * Checks the subdomain is free, holding a lock on it until the caller's transaction ends, so two sign-ups can't both claim it.
     *
     * @param  StoreRegistration|null  $except  A sign-up that may keep its own subdomain.
     *
     * @throws SubdomainTakenException When another store or sign-up has it.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureFree(string $subdomain, ?StoreRegistration $except = null): void
    {
        $connection = StoreRegistration::query()->getConnection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('A subdomain must be checked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', ['store-subdomain:'.$subdomain]);

        if ($this->isTaken($subdomain, $except)) {
            throw new SubdomainTakenException;
        }
    }

    /**
     * @param  StoreRegistration|null  $except  A sign-up that may keep its own subdomain.
     */
    public function isTaken(string $subdomain, ?StoreRegistration $except = null): bool
    {
        if (Domain::query()->where('domain', Tenant::platformDomainFor($subdomain))->exists()) {
            return true;
        }

        return StoreRegistration::query()
            ->awaitingVerification()
            ->where('subdomain', $subdomain)
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->exists();
    }

    /**
     * Whether the subdomain is on the reserved list, such as "www" or "admin".
     */
    public function isReserved(string $subdomain): bool
    {
        return in_array($subdomain, config()->array('platform.store_registration.reserved_subdomains'), true);
    }
}
