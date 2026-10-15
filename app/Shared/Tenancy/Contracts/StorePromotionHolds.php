<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Gives back a store's promotion uses reserved by checkouts whose hold ran out, for the platform's scheduled sweep. Implemented by Tenant\Promotions.
 *
 * Runs while tenancy is initialized for the store, in the same 5-minute sweep
 * as expired stock holds. Safe to run twice.
 */
interface StorePromotionHolds
{
    /**
     * @return int How many uses were given back.
     */
    public function releaseExpired(): int;
}
