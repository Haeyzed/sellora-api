<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Services;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Tenant\Pricing\Models\StoreCurrency;
use LogicException;

/**
 * The plan's presentment_currencies limit: currencies a store sells in besides its base currency. Only active ones count; the base currency never does.
 *
 * Adding a currency and turning a deactivated one back on both take a place.
 * Going over the limit after a downgrade never deactivates anything (section
 * 8); it only blocks adding or reactivating currencies.
 */
final readonly class StoreCurrencyLimit
{
    public const string LIMIT_KEY = 'presentment_currencies';

    public function __construct(private Features $features) {}

    /**
     * Checks there is room for one more active currency, holding a lock until the caller's transaction ends so two requests can't both take the last place.
     *
     * @throws UsageLimitReachedException When the store is at or over its plan's limit.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureRoomForOneMore(): void
    {
        $connection = StoreCurrency::query()->getConnection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The currency limit must be checked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', [self::LIMIT_KEY]);

        $this->features->ensureWithinLimit(self::LIMIT_KEY, StoreCurrency::query()->active()->count());
    }
}
