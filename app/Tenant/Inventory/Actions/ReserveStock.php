<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Contracts\StockLocationSelector;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Data\StockLine;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Enums\StockMovementType;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Inventory\Services\ReservationBook;
use App\Tenant\Inventory\Services\StockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Holds stock for a checkout or an order (the holder), all lines or none, so two buyers can never both get the last unit.
 *
 * For Checkout and Orders to call (section 6); nothing else writes stock.
 * Reserving again for the same holder sets each line's reservation to the new
 * quantity instead of adding another, so retrying is safe. A variant whose
 * stock isn't counted is always available and holds nothing. A variant that
 * allows backorders may be reserved beyond what is available; the part beyond
 * is recorded as backordered. One that doesn't is refused when there isn't
 * enough, for every line at once. Holds that have run out are given back
 * first, so they never block a buyer.
 */
final readonly class ReserveStock
{
    public function __construct(
        private StockLedger $stockLedger,
        private ReservationBook $reservationBook,
        private StockLocationSelector $stockLocationSelector,
    ) {}

    /**
     * @param  list<StockLine>  $lines
     * @param  CarbonImmutable|null  $expiresAt  When the hold runs out; null for one that doesn't, such as an order's.
     * @return list<StockReservation> The reservations of the counted lines.
     *
     * @throws InsufficientStockException When a line that doesn't allow backorders wants more than is available.
     */
    public function handle(StockHolder $holder, array $lines, ?CarbonImmutable $expiresAt = null, ?AccountReference $causer = null): array
    {
        return StockItem::query()->getConnection()->transaction(function () use ($holder, $lines, $expiresAt, $causer): array {
            $wanted = $this->countedLines($lines);

            if ($wanted === []) {
                return [];
            }

            $items = $this->stockLedger->lock(array_values(array_map(static fn (array $line): array => ['location' => $line['location'], 'variant' => $line['variant']], $wanted)));
            $this->releaseExpiredHolds($items);

            $existing = StockReservation::query()
                ->where('holder_type', $holder->type)
                ->where('holder_id', $holder->id)
                ->where('status', ReservationStatus::Active)
                ->where(static function (Builder $query) use ($wanted): void {
                    foreach ($wanted as $line) {
                        $query->orWhere(static fn (Builder $one) => $one->where('stock_location_id', $line['location'])->where('product_variant_id', $line['variant']));
                    }
                })
                ->orderBy('stock_location_id')
                ->orderBy('product_variant_id')
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn (StockReservation $reservation): string => ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id));

            $plans = $this->plan($wanted, $items, $existing->all());

            return array_map(fn (array $plan): StockReservation => $this->apply($plan, $holder, $expiresAt, $causer), $plans);
        });
    }

    /**
     * The lines whose stock is counted, one per variant, with the location to take them from and whether they may be backordered.
     *
     * @param  list<StockLine>  $lines
     * @return array<string, array{variant: int, variant_public_id: string, location: int, quantity: int, allows_backorder: bool}>
     */
    private function countedLines(array $lines): array
    {
        $settings = InventoryItem::query()
            ->whereIn('product_variant_id', array_map(static fn (StockLine $line): int => $line->variant->id, $lines))
            ->get()
            ->keyBy('product_variant_id');
        $wanted = [];

        foreach ($lines as $line) {
            $item = $settings->get($line->variant->id);

            if ($item instanceof InventoryItem && ! $item->tracks_stock) {
                continue;
            }

            $location = $this->stockLocationSelector->locationFor($line->variant)->id;
            $key = ReservationBook::key($location, $line->variant->id);
            $wanted[$key] = [
                'variant' => $line->variant->id,
                'variant_public_id' => $line->variant->public_id,
                'location' => $location,
                'quantity' => ($wanted[$key]['quantity'] ?? 0) + $line->quantity,
                'allows_backorder' => $item instanceof InventoryItem && $item->allows_backorder,
            ];
        }

        return $wanted;
    }

    /**
     * Gives back every hold on these stock levels whose time has run out.
     *
     * @param  array<string, StockItem>  $items
     */
    private function releaseExpiredHolds(array $items): void
    {
        $expired = StockReservation::query()
            ->where('status', ReservationStatus::Active)
            ->where('expires_at', '<=', now())
            ->where(static function (Builder $query) use ($items): void {
                foreach ($items as $item) {
                    $query->orWhere(static fn (Builder $one) => $one->where('stock_location_id', $item->stock_location_id)->where('product_variant_id', $item->product_variant_id));
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($expired as $reservation) {
            $this->reservationBook->settle($reservation, $items[ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id)], ReservationStatus::Expired);
        }
    }

    /**
     * Works out every line's change before anything is written, refusing them all if one can't be met.
     *
     * @param  array<string, array{variant: int, variant_public_id: string, location: int, quantity: int, allows_backorder: bool}>  $wanted
     * @param  array<string, StockItem>  $items
     * @param  array<string, StockReservation>  $existing
     * @return list<array{item: StockItem, reservation: StockReservation|null, quantity: int, change: int, backordered: int}>
     *
     * @throws InsufficientStockException
     */
    private function plan(array $wanted, array $items, array $existing): array
    {
        $plans = [];
        $shortfalls = [];

        foreach ($wanted as $key => $line) {
            $item = $items[$key];
            $reservation = $existing[$key] ?? null;
            $alreadyHeld = $reservation->quantity ?? 0;
            // What this holder could have: what is free, plus what it already holds.
            $availableToHolder = $item->on_hand - ($item->reserved - $alreadyHeld);
            $change = $line['quantity'] - $alreadyHeld;

            if ($change > 0 && ! $line['allows_backorder'] && $availableToHolder < $line['quantity']) {
                $shortfalls[] = ['variant_id' => $line['variant_public_id'], 'requested' => $line['quantity'], 'available' => max(0, $availableToHolder)];

                continue;
            }

            $plans[] = [
                'item' => $item,
                'reservation' => $reservation,
                'quantity' => $line['quantity'],
                'change' => $change,
                'backordered' => max(0, $line['quantity'] - max(0, $availableToHolder)),
            ];
        }

        if ($shortfalls !== []) {
            throw new InsufficientStockException($shortfalls);
        }

        return $plans;
    }

    /**
     * @param  array{item: StockItem, reservation: StockReservation|null, quantity: int, change: int, backordered: int}  $plan
     */
    private function apply(array $plan, StockHolder $holder, ?CarbonImmutable $expiresAt, ?AccountReference $causer): StockReservation
    {
        $reservation = $plan['reservation'] ?? new StockReservation;

        if (! $reservation->exists) {
            $reservation->product_variant_id = $plan['item']->product_variant_id;
            $reservation->stock_location_id = $plan['item']->stock_location_id;
            $reservation->holder_type = $holder->type;
            $reservation->holder_id = $holder->id;
            $reservation->status = ReservationStatus::Active;
        }

        $reservation->quantity = $plan['quantity'];
        $reservation->backordered_quantity = $plan['backordered'];
        $reservation->expires_at = $expiresAt;
        $reservation->save();

        if ($plan['change'] !== 0) {
            $this->stockLedger->record($plan['item'], StockMovementType::Reserved, 0, $plan['change'], causer: $causer, source: StockSource::of($reservation));
        }

        return $reservation;
    }
}
