<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Tenant\Delivery\Models\Driver;
use Illuminate\Database\DatabaseManager;
use LogicException;

/**
 * The plan's limit on drivers. Only active drivers count, so deactivating one frees a place.
 */
final readonly class DriverLimit
{
    public const string LIMIT_KEY = 'drivers';

    public function __construct(
        private Features $features,
        private DatabaseManager $database,
    ) {}

    /**
     * Checks there is room for one more active driver, holding a lock until the caller's transaction ends so two requests can't both take the last place.
     *
     * Must run inside a database transaction. The lock is per store, because
     * each store has its own database.
     *
     * @throws UsageLimitReachedException When the store's plan has no room left.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureRoomForOneMore(): void
    {
        $connection = $this->database->connection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The driver limit must be checked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', [self::LIMIT_KEY]);

        $this->features->ensureWithinLimit(self::LIMIT_KEY, Driver::query()->where('is_active', true)->count());
    }
}
