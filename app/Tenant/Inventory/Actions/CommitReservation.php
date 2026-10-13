<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Services\ReservationBook;

/**
 * Takes an order's held stock off the shelf when the order is fulfilled: on hand and reserved both go down. Safe to run twice.
 *
 * For Orders to call (section 6). Nothing can be shipped that isn't there,
 * so every reservation must be covered by what is on hand (a backordered one
 * only once the stock has arrived); otherwise none is committed.
 */
final readonly class CommitReservation
{
    public function __construct(private ReservationBook $reservationBook) {}

    /**
     * @return int How many reservations were committed.
     *
     * @throws InsufficientStockException When what is on hand doesn't cover a reservation.
     */
    public function handle(StockHolder $holder, ?AccountReference $causer = null): int
    {
        return StockItem::query()->getConnection()->transaction(function () use ($holder, $causer): int {
            ['items' => $items, 'reservations' => $reservations] = $this->reservationBook->lockActive($holder);
            $shortfalls = [];

            foreach ($reservations as $reservation) {
                $item = $items[ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id)];

                if ($item->on_hand < $reservation->quantity) {
                    $shortfalls[] = ['variant_id' => (string) $item->variant()->value('public_id'), 'requested' => $reservation->quantity, 'available' => $item->on_hand];
                }
            }

            if ($shortfalls !== []) {
                throw new InsufficientStockException($shortfalls);
            }

            foreach ($reservations as $reservation) {
                $this->reservationBook->settle($reservation, $items[ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id)], ReservationStatus::Committed, $causer);
            }

            return $reservations->count();
        });
    }
}
