<?php

declare(strict_types=1);

namespace App\Tenant\Inventory;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Inventory\Models\StockReservation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes stock reservations that ended (committed, released or expired) once the retention period has passed, in every store.
 *
 * Active reservations are never touched: they have no settled date, so the
 * cutoff never matches them. The stock movements they caused are kept for
 * good: they are the stock history.
 *
 * @extends TimestampRetentionPolicy<StockReservation>
 */
final class SettledReservationRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'settled_stock_reservations';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<StockReservation>
     */
    protected function query(): Builder
    {
        return StockReservation::query();
    }

    protected function timestampColumn(): string
    {
        return 'settled_at';
    }
}
