<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Enums\StockMovementType;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Inventory\Services\ReservationBook;
use App\Tenant\Inventory\Services\StockLedger;

/**
 * Puts stock that left the shelf for a holder back on it, held for that holder again, such as an order's delivery the driver brought back (Step 17).
 *
 * For Orders to call (section 6). On hand and reserved both go up by what
 * comes back, and the holder's active reservation of that variant grows by
 * it, or a new one is made. At most what the holder has had committed and not
 * yet returned comes back, worked out from the movements under the stock
 * levels' lock, so returning twice at once can't put more back than left. A
 * variant whose stock isn't counted never left the shelf, so nothing of it
 * comes back. Locks go stock levels first, then reservations, as everywhere.
 */
final readonly class ReturnCommittedStock
{
    public function __construct(private StockLedger $stockLedger) {}

    /**
     * @param  array<int, int>  $quantities  How many of each variant came back, by variant ID.
     * @return int How many units were put back.
     */
    public function handle(StockHolder $holder, array $quantities, ?AccountReference $causer = null): int
    {
        $quantities = array_filter($quantities, static fn (int $quantity): bool => $quantity > 0);

        if ($quantities === []) {
            return 0;
        }

        return StockItem::query()->getConnection()->transaction(function () use ($holder, $quantities, $causer): int {
            $pairs = StockReservation::query()
                ->where('holder_type', $holder->type)
                ->where('holder_id', $holder->id)
                ->whereIn('product_variant_id', array_keys($quantities))
                ->distinct()
                ->orderBy('stock_location_id')
                ->orderBy('product_variant_id')
                ->get(['stock_location_id', 'product_variant_id']);

            if ($pairs->isEmpty()) {
                return 0;
            }

            $items = $this->stockLedger->lock(array_values($pairs->map(static fn (StockReservation $pair): array => ['location' => $pair->stock_location_id, 'variant' => $pair->product_variant_id])->all()));
            $outstanding = $this->outstanding($holder, array_keys($quantities));
            $active = StockReservation::query()
                ->where('holder_type', $holder->type)
                ->where('holder_id', $holder->id)
                ->where('status', ReservationStatus::Active)
                ->whereIn('product_variant_id', array_keys($quantities))
                ->orderBy('stock_location_id')
                ->orderBy('product_variant_id')
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn (StockReservation $reservation): string => ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id));
            $returned = 0;

            foreach ($pairs as $pair) {
                $key = ReservationBook::key($pair->stock_location_id, $pair->product_variant_id);
                $quantity = min($quantities[$pair->product_variant_id], $outstanding[$key] ?? 0);

                if ($quantity < 1) {
                    continue;
                }

                $reservation = $active->get($key) ?? $this->newReservation($holder, $pair);
                $reservation->quantity += $quantity;
                $reservation->save();

                $this->stockLedger->record($items[$key], StockMovementType::Returned, $quantity, $quantity, causer: $causer, source: StockSource::of($reservation));
                $quantities[$pair->product_variant_id] -= $quantity;
                $returned += $quantity;
            }

            return $returned;
        });
    }

    /**
     * What the holder has had committed and not yet returned, by "location:variant". Read under the stock levels' lock, so it can't change meanwhile.
     *
     * @param  list<int>  $variantIds
     * @return array<string, int>
     */
    private function outstanding(StockHolder $holder, array $variantIds): array
    {
        return StockMovement::query()
            ->join('stock_reservations', static fn ($join) => $join->on('stock_reservations.public_id', '=', 'stock_movements.source_id')->where('stock_movements.source_type', '=', (new StockReservation)->getMorphClass()))
            ->where('stock_reservations.holder_type', $holder->type)
            ->where('stock_reservations.holder_id', $holder->id)
            ->whereIn('stock_movements.product_variant_id', $variantIds)
            ->whereIn('stock_movements.type', [StockMovementType::Committed->value, StockMovementType::Returned->value])
            ->groupBy('stock_movements.stock_location_id', 'stock_movements.product_variant_id')
            ->selectRaw('stock_movements.stock_location_id, stock_movements.product_variant_id, -sum(stock_movements.on_hand_delta) as outstanding')
            ->get()
            ->mapWithKeys(static fn (StockMovement $row): array => [ReservationBook::key($row->stock_location_id, $row->product_variant_id) => (int) $row->getAttribute('outstanding')])
            ->all();
    }

    private function newReservation(StockHolder $holder, StockReservation $pair): StockReservation
    {
        $reservation = new StockReservation;
        $reservation->product_variant_id = $pair->product_variant_id;
        $reservation->stock_location_id = $pair->stock_location_id;
        $reservation->holder_type = $holder->type;
        $reservation->holder_id = $holder->id;
        $reservation->status = ReservationStatus::Active;
        $reservation->quantity = 0;
        $reservation->backordered_quantity = 0;
        $reservation->expires_at = null;

        return $reservation;
    }
}
