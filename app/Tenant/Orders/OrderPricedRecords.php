<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Money\Contracts\PricedRecords;
use App\Tenant\Orders\Models\Order;

/**
 * Tells Settings whether the store has orders, which locks the base currency and tax mode (section 3.3).
 *
 * Each order keeps its own currency, rate and tax mode, but reports add up
 * orders' base totals, which would mean nothing across two base currencies.
 */
final readonly class OrderPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return Order::query()->exists();
    }
}
