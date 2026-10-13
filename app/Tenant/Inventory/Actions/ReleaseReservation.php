<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Services\ReservationBook;

/**
 * Gives back the stock a checkout or order holds, such as when an order is cancelled or a line is removed. Safe to run twice: what is already given back stays as it is.
 *
 * For Checkout and Orders to call (section 6).
 */
final readonly class ReleaseReservation
{
    public function __construct(private ReservationBook $reservationBook) {}

    /**
     * @param  list<ProductVariant>|null  $variants  Only these variants' reservations; null for all of the holder's.
     * @return int How many reservations were given back.
     */
    public function handle(StockHolder $holder, ?array $variants = null, ?AccountReference $causer = null): int
    {
        return StockItem::query()->getConnection()->transaction(function () use ($holder, $variants, $causer): int {
            $variantIds = $variants === null ? null : array_map(static fn (ProductVariant $variant): int => $variant->id, $variants);
            ['items' => $items, 'reservations' => $reservations] = $this->reservationBook->lockActive($holder, $variantIds);

            foreach ($reservations as $reservation) {
                $this->reservationBook->settle($reservation, $items[ReservationBook::key($reservation->stock_location_id, $reservation->product_variant_id)], ReservationStatus::Released, $causer);
            }

            return $reservations->count();
        });
    }
}
