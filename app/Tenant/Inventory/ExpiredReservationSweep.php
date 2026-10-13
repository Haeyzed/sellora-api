<?php

declare(strict_types=1);

namespace App\Tenant\Inventory;

use App\Shared\Tenancy\Contracts\StoreStockReservations;
use App\Tenant\Inventory\Actions\ReleaseExpiredReservations;

/**
 * The current store's part of the scheduled sweep of expired stock holds.
 */
final readonly class ExpiredReservationSweep implements StoreStockReservations
{
    public function __construct(private ReleaseExpiredReservations $releaseExpiredReservations) {}

    public function releaseExpired(): int
    {
        return $this->releaseExpiredReservations->handle(config()->integer('inventory.expired_reservations_per_run'));
    }
}
