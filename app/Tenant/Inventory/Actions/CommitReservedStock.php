<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Services\ReservationBook;
use LogicException;

/**
 * Takes part of an order's held stock off the shelf, such as what one shipment sends: on hand and reserved both go down by what is sent, and the rest stays held.
 *
 * For Orders to call (section 6) when a shipment leaves or lines that don't
 * ship are paid for. All or nothing: what is on hand must cover every part
 * (a backordered part only once its stock has arrived), otherwise nothing is
 * committed. A variant whose stock isn't counted holds nothing, so has
 * nothing to commit. Committing more of a variant than is held is a
 * programming mistake.
 */
final readonly class CommitReservedStock
{
    public function __construct(private ReservationBook $reservationBook) {}

    /**
     * @param  array<int, int>  $quantities  How many of each variant leave, by variant ID.
     * @return int How many units were committed.
     *
     * @throws InsufficientStockException When what is on hand doesn't cover a part.
     * @throws LogicException When more of a variant is committed than the holder holds.
     */
    public function handle(StockHolder $holder, array $quantities, ?AccountReference $causer = null): int
    {
        $quantities = array_filter($quantities, static fn (int $quantity): bool => $quantity > 0);

        if ($quantities === []) {
            return 0;
        }

        return StockItem::query()->getConnection()->transaction(function () use ($holder, $quantities, $causer): int {
            ['items' => $items, 'reservations' => $reservations] = $this->reservationBook->lockActive($holder, array_keys($quantities));
            $byVariant = $reservations->groupBy('product_variant_id');
            $shortfalls = [];
            $committed = 0;

            foreach ($byVariant as $variantId => $held) {
                if ($held->count() > 1) {
                    throw new LogicException('A holder holds one variant at one location only, in core.');
                }

                $reservation = $held->sole();
                $quantity = $quantities[(int) $variantId];
                $item = $items[ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id)];

                if ($quantity > $reservation->quantity) {
                    throw new LogicException('More of a variant can\'t leave than the holder holds.');
                }

                if ($item->on_hand < $quantity) {
                    $shortfalls[] = ['variant_id' => (string) ProductVariant::withTrashed()->whereKey($variantId)->value('public_id'), 'requested' => $quantity, 'available' => $item->on_hand];
                }
            }

            if ($shortfalls !== []) {
                throw new InsufficientStockException($shortfalls);
            }

            foreach ($byVariant as $variantId => $held) {
                $reservation = $held->sole();
                $quantity = $quantities[(int) $variantId];
                $item = $items[ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id)];

                $quantity === $reservation->quantity
                    ? $this->reservationBook->settle($reservation, $item, ReservationStatus::Committed, $causer)
                    : $this->reservationBook->commitPart($reservation, $item, $quantity, $causer);
                $committed += $quantity;
            }

            return $committed;
        });
    }
}
