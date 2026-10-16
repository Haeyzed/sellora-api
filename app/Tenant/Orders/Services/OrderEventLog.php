<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderEvent;

/**
 * Adds entries to an order's history, which is never changed. Data holds what changed, never contact details or addresses.
 */
final readonly class OrderEventLog
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  AccountReference|null  $actor  Who did it; null for the system (the sweep, a payment).
     */
    public function record(Order $order, OrderEventType $type, ?array $data = null, ?AccountReference $actor = null): OrderEvent
    {
        $event = new OrderEvent;
        $event->forceFill([
            'order_id' => $order->id,
            'type' => $type,
            'data' => $data,
            'actor_type' => $actor?->type,
            'actor_id' => $actor?->publicId,
        ])->save();

        return $event;
    }
}
