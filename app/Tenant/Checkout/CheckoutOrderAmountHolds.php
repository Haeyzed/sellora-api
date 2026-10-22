<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Contracts\OrderAmountHolds;
use App\Tenant\Orders\Models\Order;

/**
 * What the payment methods chosen at checkout hold or took for an order: every hold not released.
 */
final readonly class CheckoutOrderAmountHolds implements OrderAmountHolds
{
    public function heldFor(Order $order): Money
    {
        $held = CheckoutPaymentHold::query()
            ->where('order_id', $order->id)
            ->whereIn('status', array_map(static fn (PaymentHoldStatus $status): string => $status->value, array_filter(PaymentHoldStatus::cases(), static fn (PaymentHoldStatus $status): bool => $status->covers())))
            ->sum('amount_amount');

        return Money::ofMinor((int) $held, $order->currency);
    }
}
