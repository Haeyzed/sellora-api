<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * A store's part of the platform's hourly payments sweep, the safety net behind each payment's own checks with its gateway. Implemented by Tenant\Payments.
 *
 * Runs while tenancy is initialized for the store. Safe to run twice and
 * alongside webhooks.
 */
interface StorePaymentChecks
{
    /**
     * Queues a check with the gateway for every payment still waiting on it that hasn't been asked about for a while.
     *
     * @return int How many checks were queued.
     */
    public function queueOverdueChecks(): int;

    /**
     * The webhook endpoint IDs the store's gateway connections use, so the platform can prune the ones left behind.
     *
     * @return list<string>
     */
    public function webhookEndpointsInUse(): array;
}
