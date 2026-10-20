<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\OrderPaymentTotals;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Builder;

/**
 * What an order's payments add up to, and what is still due on it. Only succeeded payments count, at what they received, less what went back from them; money in review counts neither way.
 */
final readonly class OrderPayments
{
    public function totals(Order $order): OrderPaymentTotals
    {
        $paid = Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Succeeded->value)->sum('received_amount');
        $refunded = PaymentRefund::query()
            ->where('status', RefundStatus::Succeeded->value)
            ->whereHas('payment', static fn (Builder $payment) => $payment->where('order_id', $order->id)->where('status', PaymentStatus::Succeeded->value))
            ->sum('amount_amount');

        return new OrderPaymentTotals(Money::ofMinor((int) $paid, $order->currency), Money::ofMinor((int) $refunded, $order->currency));
    }

    /**
     * What is left to pay: the total less what was paid, never below zero.
     */
    public function balance(Order $order): Money
    {
        $left = $order->total->subtract($this->totals($order)->paid);

        return $left->isNegative() ? Money::zero($order->currency) : $left;
    }

    /**
     * The order's gateway payment that money may still arrive for, if any: one attempt at a time.
     */
    public function gatewayPaymentInMotion(Order $order): ?Payment
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('method', PaymentMethod::Gateway->value)
            ->whereIn('status', PaymentStatus::inMotion())
            ->orderByDesc('id')
            ->first();
    }
}
