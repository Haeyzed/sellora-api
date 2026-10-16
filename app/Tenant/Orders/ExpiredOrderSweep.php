<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Tenancy\Contracts\StoreUnpaidOrders;
use App\Tenant\Orders\Actions\CancelExpiredOrders;

/**
 * The current store's part of the scheduled sweep: unpaid orders past their payment deadline are cancelled, giving back their stock and promotion uses.
 */
final readonly class ExpiredOrderSweep implements StoreUnpaidOrders
{
    public function __construct(private CancelExpiredOrders $cancelExpiredOrders) {}

    public function cancelExpired(): int
    {
        return $this->cancelExpiredOrders->handle(config()->integer('orders.expired_orders_per_run'));
    }
}
