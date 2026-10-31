<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Shared\Money\Money;
use App\Tenant\Orders\Contracts\OrderAmountHolds;
use App\Tenant\Orders\Contracts\OrderPaymentAmounts;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Models\Order;

/**
 * Whether money really taken for an order is still unreturned: payments received and what methods made final, less what went back from both. What a method only holds isn't taken, so it is never owed back; it is released instead.
 */
final readonly class OrderRefundOwed
{
    public function __construct(
        private OrderAmountHolds $orderAmountHolds,
        private OrderPaymentAmounts $orderPaymentAmounts,
    ) {}

    public function now(Order $order): bool
    {
        return $this->given($order, $this->orderPaymentAmounts->paid($order), $this->orderPaymentAmounts->refunded($order));
    }

    /**
     * The same, from the payments' totals when the caller has them already.
     */
    public function given(Order $order, Money $paid, Money $refunded): bool
    {
        return OrderPaymentStatus::forAmounts(
            $order->total,
            $paid->add($this->orderAmountHolds->takenFor($order)),
            $refunded->add($this->orderAmountHolds->refundedFor($order)),
        )->holdsMoney();
    }
}
