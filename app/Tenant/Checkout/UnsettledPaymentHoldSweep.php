<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Tenancy\Contracts\StoreUnsettledPaymentHolds;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Checkout\Services\PaymentHolds;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * The current store's part of the hourly payments sweep: every order with a hold still held that should have settled, oldest first. A cancelled or expired order's holds are released (even when a payment arrived after it was cancelled: what was held was never taken); a paid order's are committed. Each hold under its own row lock, through PaymentHolds, so a commit or release already under way is never done twice.
 */
final readonly class UnsettledPaymentHoldSweep implements StoreUnsettledPaymentHolds
{
    public function __construct(private PaymentHolds $paymentHolds) {}

    public function settleUnsettledHolds(): int
    {
        $orderIds = CheckoutPaymentHold::query()
            ->where('status', PaymentHoldStatus::Held->value)
            ->whereHas('order', static fn (Builder $order) => $order->where('status', OrderStatus::Cancelled->value)->orWhereNotNull('paid_at'))
            ->groupBy('order_id')
            ->orderByRaw('min(id)')
            ->limit(config()->integer('checkout.hold_settle_retries_per_run'))
            ->pluck('order_id');
        $settled = 0;

        foreach (Order::query()->whereKey($orderIds)->get() as $order) {
            $settled += $order->status === OrderStatus::Cancelled ? $this->paymentHolds->release($order) : $this->paymentHolds->commit($order);
        }

        return $settled;
    }
}
