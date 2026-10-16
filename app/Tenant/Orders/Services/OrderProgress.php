<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Enums\PaymentStatus;
use App\Tenant\Orders\Events\OrderCompleted;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Moves orders along for the Actions that change them: each change locks the order's row first and works from what is saved, never from a copy read earlier, so two changes at once happen one after the other.
 */
final readonly class OrderProgress
{
    public function __construct(private OrderEventLog $orderEventLog) {}

    /**
     * The order as saved now, locked until the transaction ends. Run inside a transaction.
     */
    public function lock(Order $order): Order
    {
        return Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Completes a locked open order once it is paid and nothing is left to send. Says whether it did.
     */
    public function completeIfDone(Order $locked): bool
    {
        if ($locked->status !== OrderStatus::Open || $locked->payment_status !== PaymentStatus::Paid || ! $locked->fulfilment_status->isDone()) {
            return false;
        }

        $locked->forceFill(['status' => OrderStatus::Completed, 'completed_at' => CarbonImmutable::now()])->save();
        $this->orderEventLog->record($locked, OrderEventType::Completed, ['from' => OrderStatus::Open->value, 'to' => OrderStatus::Completed->value]);
        OrderCompleted::dispatch($locked);

        return true;
    }
}
