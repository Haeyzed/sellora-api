<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Tenancy\Contracts\StoreCommittedPaymentRefunds;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Checkout\Services\PaymentHolds;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * The current store's part of the hourly payments sweep: every cancelled order still holding money a payment method took at checkout, oldest first, has its methods asked again to give it back.
 */
final readonly class CommittedPaymentRefundSweep implements StoreCommittedPaymentRefunds
{
    public function __construct(private PaymentHolds $paymentHolds) {}

    public function retryFailedRefunds(): int
    {
        $orderIds = CheckoutPaymentHold::query()
            ->where('status', PaymentHoldStatus::Committed->value)
            ->where('amount_amount', '>', 0)
            ->whereHas('order', static fn (Builder $order) => $order->where('status', OrderStatus::Cancelled->value))
            ->groupBy('order_id')
            ->orderByRaw('min(id)')
            ->limit(config()->integer('checkout.refund_retries_per_run'))
            ->pluck('order_id');
        $givenBack = 0;

        foreach (Order::query()->whereKey($orderIds)->get() as $order) {
            $givenBack += $this->paymentHolds->refund($order);
        }

        return $givenBack;
    }
}
