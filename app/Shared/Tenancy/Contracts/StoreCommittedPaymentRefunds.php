<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Asks payment methods again to give back what they took for a store's cancelled orders, when giving it back failed before (the module couldn't be reached or refused), for the platform's hourly payments sweep. Implemented by Tenant\Checkout.
 *
 * Runs while tenancy is initialized for the store, whatever the store's or
 * the module's state: money already taken is always given back (section 3.3).
 */
interface StoreCommittedPaymentRefunds
{
    /**
     * @return int How many were given back this time.
     */
    public function retryFailedRefunds(): int;
}
