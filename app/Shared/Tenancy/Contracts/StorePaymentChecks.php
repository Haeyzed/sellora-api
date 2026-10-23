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
     * Alerts staff, once per payment, about orders paid on delivery that were delivered a while ago without their cash recorded as collected.
     *
     * @return int How many alerts were raised.
     */
    public function alertOverdueCollections(): int;

    /**
     * Alerts staff, once per part, about refunds still pending at the gateway days after they were sent.
     *
     * @return int How many alerts were raised.
     */
    public function alertSlowRefunds(): int;

    /**
     * Forgets the keys a gateway kept only for money in motion, now that it has all settled: those of a gateway disconnected meanwhile, with its webhook endpoints, and keys replaced meanwhile.
     *
     * @return int How many connections forgot keys.
     */
    public function forgetSettledCredentials(): int;

    /**
     * The webhook endpoint IDs the store's gateway connections use, so the platform can prune the ones left behind.
     *
     * @return list<string>
     */
    public function webhookEndpointsInUse(): array;
}
