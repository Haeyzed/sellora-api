<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Tenancy\StockLevelMismatch;

/**
 * Checks a store's stock levels against its movement ledger, for the platform's scheduled reconciliation (section 13). Implemented by Tenant\Inventory.
 *
 * Runs while tenancy is initialized for the store. Only reports; it never
 * changes a level.
 */
interface StoreStockLedger
{
    /**
     * @return list<StockLevelMismatch>
     */
    public function mismatches(): array;
}
