<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Resources;

use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Orders\Models\ShipmentLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipment: what it holds, how it ships, where it is, and how to follow it.
 *
 * @property Shipment $resource
 */
final class ShipmentResource extends JsonResource
{
    public function __construct(Shipment $shipment)
    {
        parent::__construct($shipment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shipment = $this->resource;

        return [
            'id' => $shipment->public_id,
            'order' => ['id' => $shipment->order->public_id, 'number' => $shipment->order->number],
            /** "ship" (a carrier), "own_delivery" (the store's drivers) or "pickup". */
            'fulfilment' => $shipment->fulfilment->value,
            /** "pending", "ready_for_pickup", "shipped", "delivered", "cancelled" or "returned" (one of the store's own deliveries that came back undelivered). */
            'status' => $shipment->status->value,
            'carrier' => $shipment->carrier,
            'carrier_service' => $shipment->carrier_service,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            /**
             * @var list<array{line: string, quantity: int}>
             */
            'lines' => array_values($shipment->lines->map(static fn (ShipmentLine $line): array => ['line' => $line->orderLine->public_id, 'quantity' => $line->quantity])->all()),
            'ready_at' => $shipment->ready_at?->toIso8601String(),
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'cancelled_at' => $shipment->cancelled_at?->toIso8601String(),
            'returned_at' => $shipment->returned_at?->toIso8601String(),
            'created_at' => $shipment->created_at?->toIso8601String(),
        ];
    }
}
