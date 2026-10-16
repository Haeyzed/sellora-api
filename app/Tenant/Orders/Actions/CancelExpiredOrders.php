<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Enums\PaymentStatus;
use App\Tenant\Orders\Models\Order;

/**
 * Cancels unpaid orders whose payment deadline has passed, a batch at a time, oldest deadline first, for the scheduled sweep. Safe to run twice and alongside payments.
 *
 * Each order is cancelled in its own short transaction, and only if it is
 * still open and unpaid once locked: a payment that arrived meanwhile wins.
 */
final readonly class CancelExpiredOrders
{
    public function __construct(private CancelOrder $cancelOrder) {}

    /**
     * @return int How many orders were cancelled.
     */
    public function handle(int $limit): int
    {
        $cancelled = 0;
        $candidates = Order::query()
            ->where('status', OrderStatus::Open->value)
            ->where('payment_status', PaymentStatus::Unpaid->value)
            ->where('payment_due_at', '<=', now())
            ->orderBy('payment_due_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($candidates as $order) {
            $cancelled += $this->cancelOrder->expire($order) ? 1 : 0;
        }

        return $cancelled;
    }
}
