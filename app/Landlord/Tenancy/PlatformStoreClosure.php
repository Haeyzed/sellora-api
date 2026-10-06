<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Actions\CloseStore;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccountReference;
use App\Shared\Tenancy\ClosedStore;
use App\Shared\Tenancy\Contracts\StoreClosure;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Closes the current store for its owner, through the same Action platform admins use.
 *
 * The closing runs with tenancy ended, so the store's status and the central
 * activity log are written to the central database, never the store's own.
 */
final readonly class PlatformStoreClosure implements StoreClosure
{
    public function __construct(private CloseStore $closeStore) {}

    /**
     * @throws StoreStatusConflictException When the store can't be closed in its current status.
     * @throws LogicException When no store is current, which is a programming mistake.
     */
    public function closeCurrentStore(AccountReference $closedBy, ?string $reason): ClosedStore
    {
        $store = tenant();

        if (! $store instanceof Tenant) {
            throw new LogicException('A store can only be closed by its owner while it is current.');
        }

        $closedStore = tenancy()->central(fn (): Tenant => $this->closeStore->handle($store, $closedBy, $reason));

        return new ClosedStore($closedStore->closed_at ?? CarbonImmutable::now(), $closedStore->purge_after ?? CarbonImmutable::now());
    }
}
