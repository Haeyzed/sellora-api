<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Inventory\Services\ReservationBook;
use App\Tenant\Inventory\Services\StockLedger;

/**
 * Gives back every hold whose time has run out, such as checkouts left open, in the current store. Safe to run twice and alongside anything else.
 *
 * Runs on a schedule. Each hold is given back in its own short transaction,
 * after locking its stock level, and only if it is still active and expired
 * by then: one that was committed, released or extended meanwhile is left
 * alone.
 */
final readonly class ReleaseExpiredReservations
{
    public function __construct(
        private StockLedger $stockLedger,
        private ReservationBook $reservationBook,
    ) {}

    /**
     * @return int How many holds were given back.
     */
    public function handle(int $limit = 500): int
    {
        $released = 0;
        $candidates = StockReservation::query()
            ->where('status', ReservationStatus::Active)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'product_variant_id', 'stock_location_id']);

        foreach ($candidates as $candidate) {
            $released += $this->releaseIfStillExpired($candidate) ? 1 : 0;
        }

        return $released;
    }

    /**
     * Gives back one hold picked as expired, only if it still is once its stock level and the hold are locked: another request may have committed, released or extended it since it was picked.
     */
    public function releaseIfStillExpired(StockReservation $candidate): bool
    {
        return StockItem::query()->getConnection()->transaction(function () use ($candidate): bool {
            $item = $this->stockLedger->lock([['location' => $candidate->stock_location_id, 'variant' => $candidate->product_variant_id]])[ReservationBook::key($candidate->stock_location_id, $candidate->product_variant_id)];
            $reservation = StockReservation::query()
                ->whereKey($candidate->id)
                ->where('status', ReservationStatus::Active)
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                return false;
            }

            $this->reservationBook->settle($reservation, $item, ReservationStatus::Expired);

            return true;
        });
    }
}
