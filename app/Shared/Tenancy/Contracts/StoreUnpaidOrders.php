<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Cancels a store's unpaid orders whose payment deadline has passed, for the platform's scheduled sweep, so no unpaid order holds stock or promotion uses forever. Implemented by Tenant\Orders.
 *
 * Runs while tenancy is initialized for the store, in the same 5-minute sweep
 * as expired stock holds. Safe to run twice and alongside payments.
 */
interface StoreUnpaidOrders
{
    /**
     * @return int How many orders were cancelled.
     */
    public function cancelExpired(): int;
}
