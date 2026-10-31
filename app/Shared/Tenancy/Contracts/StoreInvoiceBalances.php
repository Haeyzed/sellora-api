<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Tenancy\InvoiceBalanceMismatch;

/**
 * Checks a store's invoices against the sale money it has kept, for the platform's scheduled reconciliation. Implemented by Tenant\Orders.
 *
 * Runs while tenancy is initialized for the store. Only reports; it never
 * issues or changes a document.
 */
interface StoreInvoiceBalances
{
    /**
     * @return list<InvoiceBalanceMismatch>
     */
    public function mismatches(): array;
}
