<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Services\ReturnedUnits;

/**
 * Writes off units a buyer sent back that came back damaged, for Returns to call once they are inspected (Step 18).
 *
 * Recorded as they arrive at the location ("returned_damaged") and written
 * off at once ("written_off"), both with the return as their source: on hand
 * ends where it was, and the ledger shows every unit that came back.
 * Variants whose stock isn't counted are left out.
 */
final readonly class WriteOffReturnedUnits
{
    public function __construct(private ReturnedUnits $returnedUnits) {}

    /**
     * @param  array<int, int>  $quantities  How many of each variant, by variant ID.
     * @return int How many units were written off.
     */
    public function handle(StockSource $source, StockLocation $location, array $quantities, ?AccountReference $causer = null): int
    {
        return $this->returnedUnits->record($source, $location, $quantities, damaged: true, causer: $causer);
    }
}
