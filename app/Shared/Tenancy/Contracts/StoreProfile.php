<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Tenancy\StoreProfileDetails;

/**
 * The platform's copy of the current store's name, country, currency, timezone and language, while tenancy is initialized for that store.
 *
 * Implemented by Landlord, which keeps the copy on the store's row. The
 * store's settings are the source of truth (section 6): the platform's record
 * only seeds them when the store is set up, and afterwards the store sends
 * every change through a queued job that retries until update() succeeds. So
 * update() is safe to call any number of times.
 */
interface StoreProfile
{
    /**
     * The details the store was registered with, as the platform holds them now.
     */
    public function current(): StoreProfileDetails;

    /**
     * Makes the platform's copy match the store's settings. Idempotent, and does nothing for a store being purged.
     */
    public function update(StoreProfileDetails $details): void;
}
