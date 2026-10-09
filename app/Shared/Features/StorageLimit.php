<?php

declare(strict_types=1);

namespace App\Shared\Features;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use Illuminate\Database\DatabaseManager;
use LogicException;

/**
 * The plan's limit on how much space the store's uploaded files take, in megabytes. Every original file counts, from any domain; resized copies don't.
 *
 * Uploaded files are recorded in the store's media table, so the total is
 * the sum of their sizes. Going over after a downgrade never deletes
 * anything (section 8); it only blocks new uploads.
 */
final readonly class StorageLimit
{
    public const string LIMIT_KEY = 'storage_in_megabytes';

    public function __construct(
        private Features $features,
        private DatabaseManager $databases,
    ) {}

    /**
     * Checks a file of this size still fits, holding a lock until the caller's transaction ends so two uploads can't both take the last space.
     *
     * @throws UsageLimitReachedException When the file would take the store over its plan's storage.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureRoomFor(int $bytes): void
    {
        $connection = $this->databases->connection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The storage limit must be checked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', [self::LIMIT_KEY]);

        $limitInMegabytes = $this->features->limit(self::LIMIT_KEY);

        if ($limitInMegabytes === null) {
            return;
        }

        $usedBytes = (int) $connection->table('media')->sum('size');

        if ($usedBytes + $bytes > $limitInMegabytes * 1024 * 1024) {
            throw new UsageLimitReachedException(self::LIMIT_KEY, $limitInMegabytes);
        }
    }
}
