<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Contracts\PaymentCheckBeforeExpiry;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;

/**
 * Cancels unpaid orders whose payment deadline has passed, a batch at a time, oldest deadline first, for the scheduled sweep. Safe to run twice and alongside payments.
 *
 * A gateway payment still under way is asked about one last time first,
 * outside any transaction. Each order is then cancelled in its own short
 * transaction, and only if it is still open and unpaid once locked: a
 * payment that arrived meanwhile wins.
 */
final readonly class CancelExpiredOrders
{
    public function __construct(
        private CancelOrder $cancelOrder,
        private PaymentCheckBeforeExpiry $paymentCheckBeforeExpiry,
    ) {}

    /**
     * @return int How many orders were cancelled.
     */
    public function handle(int $limit): int
    {
        $cancelled = 0;
        $candidates = Order::query()
            ->where('status', OrderStatus::Open->value)
            ->whereIn('payment_status', [OrderPaymentStatus::Unpaid->value, OrderPaymentStatus::PartiallyPaid->value])
            ->where('payment_due_at', '<=', now())
            ->orderBy('payment_due_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($candidates as $order) {
            $this->paymentCheckBeforeExpiry->checkBeforeExpiry($order);
            $cancelled += $this->cancelOrder->expire($order) ? 1 : 0;
        }

        return $cancelled;
    }
}
