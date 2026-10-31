<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Contracts\OrderAmountHolds;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

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

    public function pendingFor(Order $order): Money
    {
        return $this->sum($order, [PaymentHoldStatus::Held]);
    }

    public function hasUnsettledHolds(Order $order): bool
    {
        return CheckoutPaymentHold::query()->where('order_id', $order->id)->where('status', PaymentHoldStatus::Held->value)->exists();
    }

    public function whereReleasePending(Builder $orders, bool $pending): void
    {
        $held = static fn (QueryBuilder $holds): QueryBuilder => $holds->select(DB::raw(1))
            ->from('checkout_payment_holds')
            ->whereColumn('checkout_payment_holds.order_id', 'orders.id')
            ->where('checkout_payment_holds.status', PaymentHoldStatus::Held->value)
            ->where('checkout_payment_holds.amount_amount', '>', 0);

        if ($pending) {
            $orders->where('status', OrderStatus::Cancelled->value)->whereExists($held);

            return;
        }

        $orders->where(static fn (Builder $query) => $query->where('status', '<>', OrderStatus::Cancelled->value)->orWhereNotExists($held));
    }

    public function refundedFor(Order $order): Money
    {
        return $this->sum($order, [PaymentHoldStatus::Refunded]);
    }

    public function releasedFor(Order $order): Money
    {
        return $this->sum($order, [PaymentHoldStatus::Released]);
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
