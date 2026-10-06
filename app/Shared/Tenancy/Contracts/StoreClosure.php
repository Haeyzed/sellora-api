<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Auth\AccountReference;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\ClosedStore;

/**
 * Closes the current store at its owner's request, while tenancy is initialized for that store.
 *
 * Implemented by Landlord, which owns the store's status. Never call it inside
 * an open transaction on the store's database: closing works on the central
 * database and reconnects to the store's afterwards.
 */
interface StoreClosure
{
    /**
     * @param  AccountReference  $closedBy  The owner closing it.
     *
     * @throws DomainException When the store can't be closed in its current status.
     */
    public function closeCurrentStore(AccountReference $closedBy, ?string $reason): ClosedStore;
}
