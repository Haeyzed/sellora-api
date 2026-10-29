<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Exceptions\ReturnedUnitsNoLongerOnHandException;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Services\ReturnedUnits;

/**
 * Sends a rejected return's units back to the buyer, for Returns to call in the same transaction as the rejection (Step 18).
 *
 * Inspection's movements are never edited: restocked units leave the shelf
 * again ("sent_back"), and written-off units have their write-off reversed
 * and leave ("write_off_reversed", then "sent_back"), all with the return as
 * their source, so on hand ends where it was before the inspection. Variants
 * whose stock isn't counted are left out.
 */
final readonly class SendReturnedUnitsBack
{
    public function __construct(private ReturnedUnits $returnedUnits) {}

    /**
     * @param  array<int, int>  $restocked  Units inspection put back on the shelf, by variant ID.
     * @param  array<int, int>  $writtenOff  Units inspection wrote off, by variant ID.
     * @return int How many units were sent back.
     *
     * @throws ReturnedUnitsNoLongerOnHandException When restocked units were sold or held since, so fewer are free on the shelf.
     */
    public function handle(StockSource $source, StockLocation $location, array $restocked, array $writtenOff, ?AccountReference $causer = null): int
    {
        return $this->returnedUnits->sendBack($source, $location, $restocked, $writtenOff, $causer);
    }
}
