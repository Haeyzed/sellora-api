<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Tenancy\Exceptions\StoresPerEmailLimitReachedException;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Models\Tenant;
use LogicException;

/**
 * How many stores one email may own: its stores plus its sign-ups waiting for a code, up to the limit in config/platform.php.
 *
 * Several are allowed, so agencies and merchants with several brands aren't
 * blocked, but not unlimited, to limit abuse.
 */
final readonly class StoresPerEmailLimit
{
    /**
     * Checks the email can have one more store, holding a lock on the email until the caller's transaction ends, so two sign-ups at once can't both take the last place.
     *
     * @param  StoreRegistration|null  $replacing  A sign-up becoming a store, which already holds a place.
     *
     * @throws StoresPerEmailLimitReachedException When the email has no room left.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureRoomForOneMore(string $email, ?StoreRegistration $replacing = null): void
    {
        $connection = StoreRegistration::query()->getConnection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The stores per email limit must be checked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', ['stores-per-email:'.$email]);

        $limit = config()->integer('platform.store_registration.max_stores_per_email');

        $pendingRegistrations = StoreRegistration::query()
            ->awaitingVerification()
            ->where('email', $email)
            ->when($replacing !== null, static fn ($query) => $query->whereKeyNot($replacing?->getKey()))
            ->count();

        if (Tenant::query()->where('owner_email', $email)->count() + $pendingRegistrations >= $limit) {
            throw new StoresPerEmailLimitReachedException(['limit' => $limit]);
        }
    }
}
