<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Enums\StockMovementType;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

/**
 * Finds, locks and settles stock reservations for the Actions that work with them.
 *
 * Locks are always taken in the same order as everywhere else: the stock
 * levels first (StockLedger), then the reservations, so reserving and
 * releasing at the same moment can't wait on each other in a circle.
 * Settling only ever acts on an active reservation, which is what makes
 * releasing, committing and expiring safe to run twice.
 */
final readonly class ReservationBook
{
    public function __construct(private StockLedger $stockLedger) {}

    /**
     * The holder's active reservations, optionally only for some variants, with their stock levels and the reservations themselves locked until the transaction ends.
     *
     * @param  list<int>|null  $variantIds
     * @return array{items: array<string, StockItem>, reservations: Collection<int, StockReservation>}
     */
    public function lockActive(StockHolder $holder, ?array $variantIds = null): array
    {
        $holderReservations = fn (): Builder => StockReservation::query()
            ->where('holder_type', $holder->type)
            ->where('holder_id', $holder->id)
            ->where('status', ReservationStatus::Active)
            ->when($variantIds !== null, static fn (Builder $query) => $query->whereIn('product_variant_id', $variantIds ?? []));

        $candidates = $holderReservations()->get(['product_variant_id', 'stock_location_id']);

        if ($candidates->isEmpty()) {
            return ['items' => [], 'reservations' => new Collection];
        }

        $items = $this->stockLedger->lock(array_values($candidates->map(static fn (StockReservation $reservation): array => [
            'location' => $reservation->stock_location_id,
            'variant' => $reservation->product_variant_id,
        ])->all()));

        // Read again under the lock: another transaction may have settled some meanwhile.
        $reservations = $holderReservations()
            ->orderBy('stock_location_id')
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();

        return ['items' => $items, 'reservations' => $reservations];
    }

    /**
     * Ends an active reservation: gives its stock back (released, expired) or takes it off the shelf (committed), with the movement that records it.
     *
     * @throws LogicException When the reservation isn't active or the status isn't a settled one, which is a programming mistake.
     */
    public function settle(StockReservation $reservation, StockItem $item, ReservationStatus $status, ?AccountReference $causer = null): void
    {
        if ($reservation->status !== ReservationStatus::Active || $status === ReservationStatus::Active) {
            throw new LogicException('Only an active reservation can be settled, and only into a settled status.');
        }

        $type = match ($status) {
            ReservationStatus::Committed => StockMovementType::Committed,
            ReservationStatus::Expired => StockMovementType::Expired,
            default => StockMovementType::Released,
        };
        $onHandDelta = $status === ReservationStatus::Committed ? -$reservation->quantity : 0;

        $this->stockLedger->record($item, $type, $onHandDelta, -$reservation->quantity, causer: $causer, source: StockSource::of($reservation));

        $reservation->status = $status;
        $reservation->settled_at = now()->toImmutable();
        $reservation->save();
    }

    public static function key(int $locationId, int $variantId): string
    {
        return "{$locationId}:{$variantId}";
    }
}
