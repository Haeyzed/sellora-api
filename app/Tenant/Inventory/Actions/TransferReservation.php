<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Services\ReservationBook;
use LogicException;

/**
 * Hands a checkout's held stock to the order placed from it, with no expiry: the same units stay held, so nobody can take them in between. Safe to run twice.
 *
 * For Orders to call when an order is placed (section 6). Nothing moves, so
 * no stock movement is recorded; the reservations simply change holder.
 */
final readonly class TransferReservation
{
    public function __construct(private ReservationBook $reservationBook) {}

    /**
     * @return int How many reservations changed holder.
     *
     * @throws LogicException When the new holder already holds one of the same variants, which is a programming mistake.
     */
    public function handle(StockHolder $from, StockHolder $to): int
    {
        return StockItem::query()->getConnection()->transaction(function () use ($from, $to): int {
            $reservations = $this->reservationBook->lockActive($from)['reservations'];

            if ($reservations->isNotEmpty() && $this->reservationBook->lockActive($to)['reservations']->isNotEmpty()) {
                throw new LogicException('Reservations are handed only to a holder that holds nothing yet, such as a newly placed order.');
            }

            foreach ($reservations as $reservation) {
                $reservation->holder_type = $to->type;
                $reservation->holder_id = $to->id;
                $reservation->expires_at = null;
                $reservation->save();
            }

            return $reservations->count();
        });
    }
}
