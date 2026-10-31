<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Money\Money;
use App\Tenant\Orders\Contracts\OrderPaymentAmounts;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\RefundPurpose;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Services\OrderPayments;
use Illuminate\Database\Eloquent\Builder;

/**
 * What an order's payments really brought in and gave back, as OrderPayments adds them up.
 */
final readonly class PaymentsOrderAmounts implements OrderPaymentAmounts
{
    public function __construct(private OrderPayments $orderPayments) {}

    public function paid(Order $order): Money
    {
        return $this->orderPayments->totals($order)->paid;
    }

    public function refunded(Order $order): Money
    {
        return $this->orderPayments->totals($order)->refunded;
    }

    public function saleRefunded(Order $order): Money
    {
        $amount = PaymentRefund::query()
            ->where('status', RefundStatus::Succeeded->value)
            ->whereHas('refund', static fn (Builder $refund) => $refund->where('order_id', $order->id)->where('purpose', RefundPurpose::ReducesSale->value))
            ->sum('amount_amount');

        return Money::ofMinor((int) $amount, $order->currency);
    }
}
