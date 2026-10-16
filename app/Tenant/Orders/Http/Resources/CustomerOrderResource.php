<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAddress;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a customer's own orders, as they see it: what they bought and paid, where it ships, and where it is. Never staff's notes, tags or internal details.
 *
 * @property Order $resource
 */
final class CustomerOrderResource extends JsonResource
{
    public function __construct(Order $order)
    {
        parent::__construct($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $address = $order->currentAddress(OrderAddressKind::Shipping);

        return [
            'id' => $order->public_id,
            /** The order's number, such as 1042, to quote to the store. */
            'number' => $order->number,
            /** "open", "completed" or "cancelled". */
            'status' => $order->status->value,
            /** "unpaid" or "paid". */
            'payment_status' => $order->payment_status->value,
            /** "unfulfilled", "partially_fulfilled", "fulfilled", or "not_required" when nothing ships. */
            'fulfilment_status' => $order->fulfilment_status->value,
            'currency' => $order->currency,
            /**
             * @var list<array{product_name: string, variant_name: string|null, sku: string|null, quantity: int, unit_price: MoneyResource, discount: MoneyResource, total: MoneyResource}>
             */
            'lines' => array_values($order->lines->map(static fn (OrderLine $line): array => [
                'product_name' => $line->product_name,
                'variant_name' => $line->variant_name,
                'sku' => $line->sku,
                'quantity' => $line->quantity,
                'unit_price' => new MoneyResource($line->unit_price),
                'discount' => new MoneyResource($line->discount),
                'total' => new MoneyResource($line->total),
            ])->all()),
            /**
             * @var array{subtotal: MoneyResource, discount: MoneyResource, shipping: MoneyResource, shipping_discount: MoneyResource, tax: MoneyResource, total: MoneyResource}
             */
            'totals' => [
                'subtotal' => new MoneyResource($order->subtotal),
                'discount' => new MoneyResource($order->discount),
                'shipping' => new MoneyResource($order->shipping),
                'shipping_discount' => new MoneyResource($order->shipping_discount),
                'tax' => new MoneyResource($order->tax),
                'total' => new MoneyResource($order->total),
            ],
            /** "ship", "own_delivery" or "pickup", and the method's name; null when nothing ships. */
            'shipping' => $order->shippingLine === null ? null : ['method_name' => $order->shippingLine->method_name, 'fulfilment' => $order->shippingLine->fulfilment->value],
            /**
             * @var array{recipient_name: string|null, company: string|null, line1: string|null, line2: string|null, city: string|null, state_code: string|null, postal_code: string|null, country_code: string, phone: string|null}|null
             */
            'shipping_address' => $address instanceof OrderAddress ? $address->details() : null,
            /**
             * Each shipment and how to follow it.
             *
             * @var list<array{status: string, tracking_number: string|null, tracking_url: string|null, shipped_at: string|null, delivered_at: string|null}>
             */
            'shipments' => array_values($order->shipments->map(static fn (Shipment $shipment): array => [
                'status' => $shipment->status->value,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'shipped_at' => $shipment->shipped_at?->toIso8601String(),
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            ])->all()),
            'placed_at' => $order->placed_at->toIso8601String(),
            /** Cancelled if still unpaid after this. */
            'payment_due_at' => $order->payment_due_at->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
        ];
    }
}
