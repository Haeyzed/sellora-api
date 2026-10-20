<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Events\ShipmentCreated;
use App\Tenant\Orders\Exceptions\OrderCannotBeFulfilledException;
use App\Tenant\Orders\Exceptions\ShipmentQuantityTooHighException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Orders\Models\ShipmentLine;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use Illuminate\Support\Facades\DB;

/**
 * Staff starting a shipment of a paid order, or of one paid in cash on delivery: which lines, and how many of each, go together (section 13).
 *
 * Checked under the order's lock, so two shipments made at once never send
 * more of a line than was bought: what a line has in shipments that aren't
 * cancelled never exceeds its quantity. It ships the way the buyer chose, from
 * the store's location. Delivery (Step 17) assigns drivers to shipments the
 * store delivers itself, from ShipmentCreated.
 */
final readonly class CreateShipment
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
    ) {}

    /**
     * @param  array<string, int>  $quantities  How many of each line, by the order line's public ID.
     *
     * @throws OrderCannotBeFulfilledException When the order isn't open, isn't paid (nor paid on delivery), or ships nothing.
     * @throws ShipmentQuantityTooHighException When a line would ship more than is left of it, or isn't one that ships.
     */
    public function handle(Order $order, array $quantities, AccountReference $actor, ?string $trackingNumber = null, ?string $trackingUrl = null): Shipment
    {
        return DB::transaction(function () use ($order, $quantities, $actor, $trackingNumber, $trackingUrl): Shipment {
            $locked = $this->orderProgress->lock($order);
            $shippingLine = $locked->shippingLine()->first();

            if ($locked->status !== OrderStatus::Open || ! $this->mayShip($locked) || $shippingLine === null) {
                throw new OrderCannotBeFulfilledException;
            }

            $lines = $locked->lines()->get()->keyBy('public_id')->all();
            $left = $this->orderProgress->leftToShip($locked);
            $this->ensureWithinWhatIsLeft($quantities, $lines, $left);

            $shipment = new Shipment;
            $shipment->forceFill([
                'order_id' => $locked->id,
                'fulfilment' => $shippingLine->fulfilment,
                'stock_location_id' => StockLocation::query()->where('is_default', true)->sole()->id,
                'status' => ShipmentStatus::Pending,
                'carrier' => $shippingLine->carrier,
                'carrier_service' => $shippingLine->carrier_service,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'created_by_type' => $actor->type,
                'created_by_id' => $actor->publicId,
            ])->save();

            foreach ($quantities as $linePublicId => $quantity) {
                (new ShipmentLine)->forceFill(['shipment_id' => $shipment->id, 'order_line_id' => $lines[$linePublicId]->id, 'quantity' => $quantity])->save();
            }

            $this->orderEventLog->record($locked, OrderEventType::ShipmentCreated, ['shipment' => $shipment->public_id], $actor);
            ShipmentCreated::dispatch($shipment);

            return $shipment->load('lines.orderLine');
        });
    }

    /**
     * Paid in full, or paid in cash when it arrives.
     */
    private function mayShip(Order $locked): bool
    {
        return $locked->pay_on_delivery || $locked->payment_status->wasPaidInFull();
    }

    /**
     * @param  array<string, int>  $quantities
     * @param  array<string, OrderLine>  $lines
     * @param  array<int, int>  $left  What is left to ship of each line that ships, by its database ID.
     *
     * @throws ShipmentQuantityTooHighException
     */
    private function ensureWithinWhatIsLeft(array $quantities, array $lines, array $left): void
    {
        $tooHigh = [];

        foreach ($quantities as $linePublicId => $quantity) {
            $line = $lines[$linePublicId] ?? null;

            // Only lines that ship have anything left to ship; any other line has 0.
            if ($line === null || $quantity < 1 || $quantity > ($left[$line->id] ?? 0)) {
                $tooHigh[$linePublicId] = $line === null ? 0 : ($left[$line->id] ?? 0);
            }
        }

        if ($quantities === [] || $tooHigh !== []) {
            throw new ShipmentQuantityTooHighException($tooHigh);
        }
    }
}
