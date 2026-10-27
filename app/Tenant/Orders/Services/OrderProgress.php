<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Orders\Enums\FulfilmentStatus;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Events\OrderCompleted;
use App\Tenant\Orders\Events\OrderFulfilled;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\ShipmentLine;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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
        if ($locked->status !== OrderStatus::Open || ! $locked->payment_status->wasPaidInFull() || ! $locked->fulfilment_status->isDone()) {
            return false;
        }

        $locked->forceFill(['status' => OrderStatus::Completed, 'completed_at' => CarbonImmutable::now()])->save();
        $this->orderEventLog->record($locked, OrderEventType::Completed, ['from' => OrderStatus::Open->value, 'to' => OrderStatus::Completed->value]);
        OrderCompleted::dispatch($locked);

        return true;
    }

    /**
     * What is left to ship of each line that ships, by its database ID: its quantity less what its shipments that aren't cancelled or returned hold. Run under the order's lock.
     *
     * @return array<int, int>
     */
    public function leftToShip(Order $locked): array
    {
        $allocated = ShipmentLine::query()
            ->join('shipments', 'shipments.id', '=', 'shipment_lines.shipment_id')
            ->where('shipments.order_id', $locked->id)
            ->whereNotIn('shipments.status', self::endedWithoutDelivery())
            ->groupBy('shipment_lines.order_line_id')
            ->selectRaw('shipment_lines.order_line_id, sum(shipment_lines.quantity) as allocated')
            ->pluck('allocated', 'order_line_id');
        $left = [];

        foreach ($locked->lines()->where('requires_shipping', true)->get(['id', 'quantity']) as $line) {
            $left[$line->id] = $line->quantity - (int) ($allocated[$line->id] ?? 0);
        }

        return $left;
    }

    /**
     * Whether the order has a shipment that isn't cancelled or returned.
     */
    public function hasShipments(Order $locked): bool
    {
        return $locked->shipments()->whereNotIn('status', self::endedWithoutDelivery())->exists();
    }

    /**
     * Whether one of the order's shipments is with a carrier or driver right now: then it can't be cancelled.
     */
    public function hasShipmentUnderway(Order $locked): bool
    {
        return $locked->shipments()->where('status', ShipmentStatus::Shipped->value)->exists();
    }

    /**
     * When an unpaid order is cancelled: its payment deadline, or, once one of its deliveries has come back, the same time again from the latest return (Step 17).
     */
    public function paymentDeadline(Order $order): CarbonImmutable
    {
        $lastReturned = $order->shipments()->max('returned_at');

        if ($lastReturned === null) {
            return $order->payment_due_at;
        }

        return CarbonImmutable::parse($lastReturned)->add($order->placed_at->diff($order->payment_due_at));
    }

    /**
     * Statuses of shipments that ended without reaching the buyer, so hold nothing of the order.
     *
     * @return list<string>
     */
    public static function endedWithoutDelivery(): array
    {
        return [ShipmentStatus::Cancelled->value, ShipmentStatus::Returned->value];
    }

    /**
     * Works a locked open order's fulfilment out again from its shipments that count as fulfilled (handed to a carrier, or delivered and collected; one of the store's own deliveries only once delivered), and completes it when paid and fulfilled.
     */
    public function recomputeFulfilment(Order $locked): void
    {
        if ($locked->status !== OrderStatus::Open || $locked->fulfilment_status === FulfilmentStatus::NotRequired) {
            return;
        }

        $sent = ShipmentLine::query()
            ->join('shipments', 'shipments.id', '=', 'shipment_lines.shipment_id')
            ->where('shipments.order_id', $locked->id)
            ->where(static fn (Builder $sent) => $sent
                ->where('shipments.status', ShipmentStatus::Delivered->value)
                ->orWhere(static fn (Builder $carrier) => $carrier->where('shipments.status', ShipmentStatus::Shipped->value)->where('shipments.fulfilment', ShippingFulfilment::Ship->value)))
            ->groupBy('shipment_lines.order_line_id')
            ->selectRaw('shipment_lines.order_line_id, sum(shipment_lines.quantity) as sent')
            ->pluck('sent', 'order_line_id');
        $lines = $locked->lines()->where('requires_shipping', true)->get(['id', 'quantity']);
        $allSent = $lines->every(static fn (OrderLine $line): bool => (int) ($sent[$line->id] ?? 0) >= $line->quantity);
        $anySent = $sent->isNotEmpty();

        $status = match (true) {
            $allSent => FulfilmentStatus::Fulfilled,
            $anySent => FulfilmentStatus::PartiallyFulfilled,
            default => FulfilmentStatus::Unfulfilled,
        };

        if ($status === $locked->fulfilment_status) {
            return;
        }

        $locked->forceFill(['fulfilment_status' => $status, 'fulfilled_at' => $status === FulfilmentStatus::Fulfilled ? CarbonImmutable::now() : null])->save();

        if ($status === FulfilmentStatus::Fulfilled) {
            $this->orderEventLog->record($locked, OrderEventType::Fulfilled);
            OrderFulfilled::dispatch($locked);
            $this->completeIfDone($locked);
        }
    }
}
