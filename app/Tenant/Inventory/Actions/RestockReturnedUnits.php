<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Services\ReturnedUnits;

/**
 * Puts units a buyer sent back on the shelf at a location, ready to sell again, for Returns to call once they are inspected (Step 18).
 *
 * On hand goes up by what came back, recorded as "restocked" with the return
 * as its source; nothing is held for anyone. Variants whose stock isn't
 * counted are left out.
 */
final readonly class RestockReturnedUnits
{
    public function __construct(private ReturnedUnits $returnedUnits) {}

    /**
     * @param  array<int, int>  $quantities  How many of each variant, by variant ID.
     * @return int How many units went back on the shelf.
     */
    public function handle(StockSource $source, StockLocation $location, array $quantities, ?AccountReference $causer = null): int
    {
        return $this->returnedUnits->record($source, $location, $quantities, damaged: false, causer: $causer);
    }
}
