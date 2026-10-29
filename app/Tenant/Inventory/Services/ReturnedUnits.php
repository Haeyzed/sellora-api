<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Enums\StockMovementType;
use App\Tenant\Inventory\Exceptions\ReturnedUnitsNoLongerOnHandException;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;

/**
 * Records units a buyer sent back (Step 18) at a location: back on the shelf, or written off as damaged; and, when the return is then rejected, the same units going back to the buyer.
 *
 * A variant whose stock isn't counted never left the shelf, so nothing of it
 * is recorded. Levels are locked by location then variant, as everywhere.
 * How many may come back is the caller's rule (Returns), checked under its
 * own lock: this only records what it is told.
 */
final readonly class ReturnedUnits
{
    public function __construct(private StockLedger $stockLedger) {}

    /**
     * @param  array<int, int>  $quantities  How many of each variant, by variant ID.
     * @return int How many units were recorded.
     */
    public function record(StockSource $source, StockLocation $location, array $quantities, bool $damaged, ?AccountReference $causer): int
    {
        $quantities = array_filter($quantities, static fn (int $quantity): bool => $quantity > 0);
        $uncounted = InventoryItem::query()->whereIn('product_variant_id', array_keys($quantities))->where('tracks_stock', false)->pluck('product_variant_id')->all();
        $quantities = array_diff_key($quantities, array_flip($uncounted));
        ksort($quantities);

        if ($quantities === []) {
            return 0;
        }

        return StockItem::query()->getConnection()->transaction(function () use ($source, $location, $quantities, $damaged, $causer): int {
            $items = $this->stockLedger->lock(array_map(static fn (int $variant): array => ['location' => $location->id, 'variant' => $variant], array_keys($quantities)));

            foreach ($quantities as $variant => $quantity) {
                $item = $items["{$location->id}:{$variant}"];

                if ($damaged) {
                    $this->stockLedger->record($item, StockMovementType::ReturnedDamaged, $quantity, causer: $causer, source: $source);
                    $this->stockLedger->record($item, StockMovementType::WrittenOff, -$quantity, causer: $causer, source: $source);
                } else {
                    $this->stockLedger->record($item, StockMovementType::Restocked, $quantity, causer: $causer, source: $source);
                }
            }

            return array_sum($quantities);
        });
    }

    /**
     * Sends a rejected return's units back to the buyer from the location they were inspected at, as new movements: each restocked unit leaves the shelf again, and each written-off unit has its write-off reversed and leaves. On hand ends where it was before the inspection.
     *
     * @param  array<int, int>  $restocked  Units put back on the shelf, by variant ID.
     * @param  array<int, int>  $writtenOff  Units written off, by variant ID.
     * @return int How many units were sent back.
     *
     * @throws ReturnedUnitsNoLongerOnHandException When fewer restocked units are free on the shelf than are to leave.
     */
    public function sendBack(StockSource $source, StockLocation $location, array $restocked, array $writtenOff, ?AccountReference $causer): int
    {
        $restocked = array_filter($restocked, static fn (int $quantity): bool => $quantity > 0);
        $writtenOff = array_filter($writtenOff, static fn (int $quantity): bool => $quantity > 0);
        $variants = array_unique([...array_keys($restocked), ...array_keys($writtenOff)]);
        $uncounted = array_flip(InventoryItem::query()->whereIn('product_variant_id', $variants)->where('tracks_stock', false)->pluck('product_variant_id')->all());
        $restocked = array_diff_key($restocked, $uncounted);
        $writtenOff = array_diff_key($writtenOff, $uncounted);
        $variants = array_values(array_unique([...array_keys($restocked), ...array_keys($writtenOff)]));
        sort($variants);

        if ($variants === []) {
            return 0;
        }

        return StockItem::query()->getConnection()->transaction(function () use ($source, $location, $restocked, $writtenOff, $variants, $causer): int {
            $items = $this->stockLedger->lock(array_map(static fn (int $variant): array => ['location' => $location->id, 'variant' => $variant], $variants));

            foreach ($restocked as $variant => $quantity) {
                $item = $items["{$location->id}:{$variant}"];

                if ($item->on_hand - $item->reserved < $quantity) {
                    throw new ReturnedUnitsNoLongerOnHandException;
                }
            }

            foreach ($variants as $variant) {
                $item = $items["{$location->id}:{$variant}"];

                if (($writtenOff[$variant] ?? 0) > 0) {
                    $this->stockLedger->record($item, StockMovementType::WriteOffReversed, $writtenOff[$variant], causer: $causer, source: $source);
                }

                $this->stockLedger->record($item, StockMovementType::SentBack, -(($restocked[$variant] ?? 0) + ($writtenOff[$variant] ?? 0)), causer: $causer, source: $source);
            }

            return array_sum($restocked) + array_sum($writtenOff);
        });
    }
}
