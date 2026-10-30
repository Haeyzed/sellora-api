<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Contracts\OrderAmountHolds;
use App\Tenant\Orders\Models\Order;

/**
 * What the payment methods chosen at checkout hold or took for an order (every hold not released), what of it they took (committed or refunded holds), and what they gave back (refunded holds).
 */
final readonly class CheckoutOrderAmountHolds implements OrderAmountHolds
{
    public function heldFor(Order $order): Money
    {
        return $this->sum($order, array_values(array_filter(PaymentHoldStatus::cases(), static fn (PaymentHoldStatus $status): bool => $status->covers())));
    }

    public function takenFor(Order $order): Money
    {
        return $this->sum($order, [PaymentHoldStatus::Committed, PaymentHoldStatus::Refunded]);
    }

    public function refundedFor(Order $order): Money
    {
        return $this->sum($order, [PaymentHoldStatus::Refunded]);
    }

    /**
     * @param  list<PaymentHoldStatus>  $statuses
     */
    private function sum(Order $order, array $statuses): Money
    {
        $amount = CheckoutPaymentHold::query()
            ->where('order_id', $order->id)
            ->whereIn('status', array_map(static fn (PaymentHoldStatus $status): string => $status->value, $statuses))
            ->sum('amount_amount');

        return Money::ofMinor((int) $amount, $order->currency);
    }
}
