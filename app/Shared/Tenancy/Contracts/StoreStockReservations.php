<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Gives back a store's expired stock holds, for the platform's scheduled sweep. Implemented by Tenant\Inventory.
 *
 * Runs while tenancy is initialized for the store. Safe to run twice.
 */
interface StoreStockReservations
{
    /**
     * @return int How many holds were given back.
     */
    public function releaseExpired(): int;
}
