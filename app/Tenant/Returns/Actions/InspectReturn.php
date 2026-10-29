<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Actions\RestockReturnedUnits;
use App\Tenant\Inventory\Actions\WriteOffReturnedUnits;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnInspectionMismatchException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Services\ReturnProgress;
use Illuminate\Support\Facades\DB;

/**
 * Staff checking a received return's items at a location: each unit goes back on the shelf or is written off as damaged, through Inventory, with the return as the source. ReturnInspected is announced once it is saved.
 *
 * The return is locked before stock levels (the store's lock order), and
 * checked as saved under its lock, so two inspections at the same moment
 * never both move stock.
 */
final readonly class InspectReturn
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private RestockReturnedUnits $restockReturnedUnits,
        private WriteOffReturnedUnits $writeOffReturnedUnits,
    ) {}

    /**
     * @param  array<string, array{restocked: int, written_off: int}>  $outcomes  What became of each line's units, by order line public ID; every line of the return, each adding up to its quantity.
     *
     * @throws ReturnTransitionNotAllowedException When it isn't received.
     * @throws ReturnInspectionMismatchException When a line is missing, isn't the return's, or doesn't add up.
     */
    public function handle(OrderReturn $return, StockLocation $location, array $outcomes, AccountReference $by): OrderReturn
    {
        return DB::transaction(function () use ($return, $location, $outcomes, $by): OrderReturn {
            $locked = $this->returnProgress->lock($return);

            if (! $locked->status->canMoveTo(ReturnStatus::Inspected)) {
                throw new ReturnTransitionNotAllowedException;
            }

            $lines = $locked->lines()->with('orderLine')->get()->keyBy(static fn (ReturnLine $line): string => $line->orderLine->public_id);
            $wrong = array_values(array_diff(array_keys($outcomes), $lines->keys()->all()));

            foreach ($lines as $publicId => $line) {
                $outcome = $outcomes[$publicId] ?? null;

                if ($outcome === null || $line->quantity !== $outcome['restocked'] + $outcome['written_off']) {
                    $wrong[] = $publicId;
                }
            }

            if ($wrong !== []) {
                throw new ReturnInspectionMismatchException($wrong);
            }

            $restocked = [];
            $writtenOff = [];

            foreach ($lines as $publicId => $line) {
                $line->forceFill(['restocked_quantity' => $outcomes[$publicId]['restocked'], 'written_off_quantity' => $outcomes[$publicId]['written_off']])->save();
                $variantId = $line->orderLine->product_variant_id;
                $restocked[$variantId] = ($restocked[$variantId] ?? 0) + $outcomes[$publicId]['restocked'];
                $writtenOff[$variantId] = ($writtenOff[$variantId] ?? 0) + $outcomes[$publicId]['written_off'];
            }

            $source = StockSource::of($locked);
            $this->restockReturnedUnits->handle($source, $location, $restocked, $by);
            $this->writeOffReturnedUnits->handle($source, $location, $writtenOff, $by);

            return $this->returnProgress->move($locked, ReturnStatus::Inspected, $by, ['stock_location_id' => $location->id]);
        });
    }
}
