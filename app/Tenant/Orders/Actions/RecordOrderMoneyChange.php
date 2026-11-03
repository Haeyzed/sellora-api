<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Notes that an order's money changed just now, which starts the invoice check's grace period again. Only for a real change: a retry that moved nothing never calls this.
 */
final readonly class RecordOrderMoneyChange
{
    public function handle(int $orderId): void
    {
        Order::query()->whereKey($orderId)->update(['money_changed_at' => CarbonImmutable::now()]);
    }
}
