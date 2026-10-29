<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Settles what payment methods still hold for a store's orders when settling it failed before (the method threw, or is no longer registered), for the platform's hourly payments sweep: committed for orders that were paid, released for orders that expired or were cancelled. Implemented by Tenant\Checkout.
 *
 * Runs while tenancy is initialized for the store, whatever the store's or
 * the module's state: money already in motion is always looked after
 * (section 3.3).
 */
interface StoreUnsettledPaymentHolds
{
    /**
     * @return int How many holds were settled this time.
     */
    public function settleUnsettledHolds(): int;
}
