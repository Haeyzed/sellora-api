<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

/**
 * Issues a store's tax documents that are due but missing, for the platform's hourly sweep: the safety net behind issuing them as each sale is paid. Implemented by Tenant\Orders.
 *
 * Runs while tenancy is initialized for the store. Safe to run twice and
 * alongside the queue, which issues each document as it falls due.
 */
interface StoreTaxDocuments
{
    /**
     * @return int How many documents were issued.
     */
    public function issueMissing(): int;
}
