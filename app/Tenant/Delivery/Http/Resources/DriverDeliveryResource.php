<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Models\ShipmentLine;
use App\Tenant\Payments\Actions\FindCashToCollect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivery as its driver sees it in the driver app: where to collect it, what it holds, where it goes, and how much cash to collect.
 *
 * The recipient's address and phone number are shown only while the
 * delivery is under way; once it is delivered, cancelled or resolved, the
 * driver no longer sees them.
 *
 * @property Delivery $resource
 */
final class DriverDeliveryResource extends JsonResource
{
    public function __construct(Delivery $delivery)
    {
        parent::__construct($delivery);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $delivery = $this->resource;
        $shipment = $delivery->shipment;
        $order = $shipment->order;
        $open = $delivery->isOpen();
        $address = $open ? $order->currentAddress(OrderAddressKind::Shipping) : null;
        $cash = $open ? app(FindCashToCollect::class)->handle($order) : null;

        return [
            'id' => $delivery->public_id,
            'order_number' => $order->number,
            /** A test order: nothing real was bought, and no cash is collected. */
            'is_test' => $order->is_test,
            /** "assigned", "picked_up", "on_the_way", "delivered", "failed" or "cancelled". */
            'status' => $delivery->status->value,
            'attempt' => $delivery->attempt,
            /** Where to collect the goods. */
            'pickup_location' => ['name' => $shipment->stockLocation->name],
            /**
             * @var list<array{name: string, variant: string|null, quantity: int}>
             */
            'items' => array_values($shipment->lines->map(static fn (ShipmentLine $line): array => [
                'name' => $line->orderLine->product_name,
                'variant' => $line->orderLine->variant_name,
                'quantity' => $line->quantity,
            ])->all()),
            /** Where it goes and who to call; null once the delivery is no longer under way. */
            'recipient' => $address?->details(),
            /** Cash to collect from the recipient, in minor units; null when there is none to collect. */
            'cash_to_collect' => $cash === null ? null : new MoneyResource($cash),
            /** Why it failed; null unless it did. */
            'failure_reason' => $delivery->failure_reason?->value,
            'assigned_at' => $delivery->assigned_at->toIso8601String(),
            'picked_up_at' => $delivery->picked_up_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
        ];
    }
}
