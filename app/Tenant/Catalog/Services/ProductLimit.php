<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Tenant\Catalog\Models\Product;
use LogicException;

/**
 * The plan's limit on products. Every product outside the trash counts, drafts and archived ones included; variants don't.
 *
 * Going over the limit after a downgrade never deletes anything (section 8);
 * it only blocks adding or restoring products.
 */
final readonly class ProductLimit
{
    public const string LIMIT_KEY = 'products';

    public function __construct(private Features $features) {}

    /**
     * Checks there is room for one more product, holding a lock until the caller's transaction ends so two requests can't both take the last place.
     *
     * @throws UsageLimitReachedException When the store's plan has no room left.
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function ensureRoomForOneMore(): void
    {
        $connection = Product::query()->getConnection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The product limit must be checked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', [self::LIMIT_KEY]);

        $this->features->ensureWithinLimit(self::LIMIT_KEY, Product::query()->count());
    }
}
