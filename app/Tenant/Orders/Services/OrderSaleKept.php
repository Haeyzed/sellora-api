<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Shared\Money\Money;
use App\Tenant\Orders\Contracts\OrderAmountHolds;
use App\Tenant\Orders\Contracts\OrderPaymentAmounts;
use App\Tenant\Orders\Models\Order;

/**
 * The sale money a store has kept for an order: what counted toward its total, payments received and what methods hold or took, less what went back from it. What was paid over the total, and a refund of that, were never part of the sale. An invoice less its credit notes must equal this.
 */
final readonly class OrderSaleKept
{
    public function __construct(
        private OrderAmountHolds $orderAmountHolds,
        private OrderPaymentAmounts $orderPaymentAmounts,
    ) {}

    public function for(Order $order): Money
    {
        // What a method gave back comes off before the cap: it may be what took the order over its total.
        $counted = $this->counted($order);
        $sale = $counted->isLessThan($order->total) ? $counted : $order->total;
        $kept = $sale->subtract($this->orderPaymentAmounts->saleRefunded($order));

        return $kept->isNegative() ? Money::zero($order->currency) : $kept;
    }

    /**
     * Whether what stopped counting left the order short of its total. A hold released or given back is the only thing that can take a paid order below it without a refund saying so; an order with neither stands as its payment status says.
     */
    public function lostItsCover(Order $order): bool
    {
        $stoppedCounting = $this->orderAmountHolds->releasedFor($order)->add($this->orderAmountHolds->refundedFor($order));

        return $stoppedCounting->isPositive() && $this->counted($order)->isLessThan($order->total);
    }

    private function counted(Order $order): Money
    {
        return $this->orderPaymentAmounts->paid($order)->add($this->orderAmountHolds->heldFor($order))->subtract($this->orderAmountHolds->refundedFor($order));
    }
}
