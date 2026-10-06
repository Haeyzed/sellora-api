<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Signs everyone out of the current store, while tenancy is initialized for that store.
 *
 * Implemented in the store zone, which owns its accounts' tokens, so the
 * platform side never deletes rows in a store's database itself.
 */
interface StoreSessions
{
    /**
     * Revokes every sign-in in the store: staff, customers and drivers.
     */
    public function endAll(): void;
}
