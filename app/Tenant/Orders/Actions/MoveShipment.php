<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Actions\CommitReservedStock;
use App\Tenant\Inventory\Actions\ReturnCommittedStock;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Contracts\ShipmentDrivers;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Events\ShipmentCancelled;
use App\Tenant\Orders\Events\ShipmentDelivered;
use App\Tenant\Orders\Events\ShipmentReadyForPickup;
use App\Tenant\Orders\Events\ShipmentReturned;
use App\Tenant\Orders\Events\ShipmentShipped;
use App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException;
use App\Tenant\Orders\Exceptions\ShipmentWithDriverException;
use App\Tenant\Orders\Exceptions\TrackingNumberRequiredException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Orders\Models\ShipmentLine;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Moves a shipment along: ready for pickup, shipped, delivered (or collected), cancelled before it leaves, or returned undelivered. For staff, and for Delivery's drivers (Step 17).
 *
 * Each move locks the order, then the shipment, and works from what is saved,
 * so moves at the same moment happen one after the other. Stock leaves the
 * shelf when the shipment ships, or when a pickup is collected, and comes
 * back when one of the store's own deliveries is returned. The order's
 * fulfilment is worked out again after each move; it completes once paid and
 * fulfilled.
 */
final readonly class MoveShipment
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private CommitReservedStock $commitReservedStock,
        private ReturnCommittedStock $returnCommittedStock,
        private ShipmentDrivers $shipmentDrivers,
    ) {}

    /**
     * A pickup is waiting at the store for its buyer.
     *
     * @throws ShipmentTransitionNotAllowedException
     */
    public function ready(Shipment $shipment, AccountReference $actor): Shipment
    {
        return $this->move($shipment, ShipmentStatus::ReadyForPickup, $actor, static function (Shipment $locked): void {
            $locked->ready_at = CarbonImmutable::now();
        }, static fn (Shipment $moved) => ShipmentReadyForPickup::dispatch($moved));
    }

    /**
     * Handed to the carrier or driver: its stock leaves the shelf. A carrier shipment needs its tracking number first.
     *
     * @param  bool  $byDriver  Delivery moving it for one of the store's drivers; otherwise one a driver has is refused.
     *
     * @throws ShipmentTransitionNotAllowedException
     * @throws TrackingNumberRequiredException
     * @throws ShipmentWithDriverException When one of the store's drivers has it, and it isn't their delivery moving it.
     * @throws InsufficientStockException When what it holds isn't on hand, such as a backorder that hasn't arrived.
     */
    public function ship(Shipment $shipment, AccountReference $actor, bool $byDriver = false): Shipment
    {
        return $this->move($shipment, ShipmentStatus::Shipped, $actor, function (Shipment $locked, Order $order) use ($actor, $byDriver): void {
            $this->ensureNotWithDriver($locked, $byDriver);

            if ($locked->fulfilment === ShippingFulfilment::Ship && ($locked->tracking_number === null || $locked->tracking_number === '')) {
                throw new TrackingNumberRequiredException;
            }

            $this->commitStock($locked, $order, $actor);
            $locked->shipped_at = CarbonImmutable::now();
        }, static fn (Shipment $moved) => ShipmentShipped::dispatch($moved));
    }

    /**
     * Arrived, or collected: a collected pickup's stock leaves the shelf now.
     *
     * @param  bool  $byDriver  Delivery moving it for one of the store's drivers; otherwise one a driver has is refused.
     *
     * @throws ShipmentTransitionNotAllowedException
     * @throws ShipmentWithDriverException When one of the store's drivers has it, and it isn't their delivery moving it.
     * @throws InsufficientStockException When a collected pickup's stock isn't on hand.
     */
    public function deliver(Shipment $shipment, AccountReference $actor, bool $byDriver = false): Shipment
    {
        return $this->move($shipment, ShipmentStatus::Delivered, $actor, function (Shipment $locked, Order $order) use ($actor, $byDriver): void {
            $this->ensureNotWithDriver($locked, $byDriver);

            if ($locked->fulfilment === ShippingFulfilment::Pickup) {
                $this->commitStock($locked, $order, $actor);
            }

            $locked->delivered_at = CarbonImmutable::now();
        }, static fn (Shipment $moved) => ShipmentDelivered::dispatch($moved));
    }

    /**
     * Never left: what it held can ship again.
     *
     * @throws ShipmentTransitionNotAllowedException
     */
    public function cancel(Shipment $shipment, AccountReference $actor): Shipment
    {
        return $this->move($shipment, ShipmentStatus::Cancelled, $actor, static function (Shipment $locked): void {
            $locked->cancelled_at = CarbonImmutable::now();
        }, static fn (Shipment $moved) => ShipmentCancelled::dispatch($moved));
    }

    /**
     * One of the store's own deliveries came back undelivered: what it held goes back on the shelf, held for the order again, so it can ship once more or be given back if the order is cancelled. For Delivery (Step 17).
     *
     * @throws ShipmentTransitionNotAllowedException When it isn't an own delivery that has shipped.
     */
    public function returnToStore(Shipment $shipment, AccountReference $actor): Shipment
    {
        return $this->move($shipment, ShipmentStatus::Returned, $actor, function (Shipment $locked, Order $order) use ($actor): void {
            $this->returnCommittedStock->handle(StockHolder::of($order), $this->quantities($locked), $actor);
            $locked->returned_at = CarbonImmutable::now();
        }, static fn (Shipment $moved) => ShipmentReturned::dispatch($moved));
    }

    /**
     * @param-immediately-invoked-callable $apply
     * @param-immediately-invoked-callable $announce
     *
     * @param  callable(Shipment, Order): void  $apply
     * @param  (callable(Shipment): mixed)|null  $announce
     *
     * @throws ShipmentTransitionNotAllowedException
     */
    private function move(Shipment $shipment, ShipmentStatus $next, AccountReference $actor, callable $apply, ?callable $announce = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $next, $actor, $apply, $announce): Shipment {
            $order = $this->orderProgress->lock($shipment->order()->firstOrFail());
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canMoveTo($next, $locked->fulfilment)) {
                throw new ShipmentTransitionNotAllowedException;
            }

            $from = $locked->status;
            $apply($locked, $order);
            $locked->status = $next;
            $locked->save();

            $this->orderEventLog->record($order, self::eventFor($next), ['shipment' => $locked->public_id, 'from' => $from->value, 'to' => $next->value], $actor);

            if ($announce !== null) {
                $announce($locked);
            }

            $this->orderProgress->recomputeFulfilment($order);

            return $locked->load('lines.orderLine');
        });
    }

    /**
     * Staff can't mark a shipment one of the store's drivers has shipped or delivered: the driver's delivery moves it. Asked under the order's and the shipment's locks, which assigning a driver also takes.
     *
     * @throws ShipmentWithDriverException
     */
    private function ensureNotWithDriver(Shipment $locked, bool $byDriver): void
    {
        if (! $byDriver && $locked->fulfilment === ShippingFulfilment::OwnDelivery && $this->shipmentDrivers->hasDelivery($locked)) {
            throw new ShipmentWithDriverException;
        }
    }

    /**
     * @throws InsufficientStockException
     */
    private function commitStock(Shipment $locked, Order $order, AccountReference $actor): void
    {
        $this->commitReservedStock->handle(StockHolder::of($order), $this->quantities($locked), $actor);
    }

    /**
     * How many of each variant it holds, by variant ID.
     *
     * @return array<int, int>
     */
    private function quantities(Shipment $locked): array
    {
        $quantities = [];

        foreach ($locked->lines()->with('orderLine')->get() as $line) {
            /** @var ShipmentLine $line */
            $variant = $line->orderLine->product_variant_id;
            $quantities[$variant] = ($quantities[$variant] ?? 0) + $line->quantity;
        }

        return $quantities;
    }

    private static function eventFor(ShipmentStatus $status): OrderEventType
    {
        return match ($status) {
            ShipmentStatus::ReadyForPickup => OrderEventType::ShipmentReady,
            ShipmentStatus::Shipped => OrderEventType::ShipmentShipped,
            ShipmentStatus::Delivered => OrderEventType::ShipmentDelivered,
            ShipmentStatus::Returned => OrderEventType::ShipmentReturned,
            ShipmentStatus::Cancelled, ShipmentStatus::Pending => OrderEventType::ShipmentCancelled,
        };
    }
}
