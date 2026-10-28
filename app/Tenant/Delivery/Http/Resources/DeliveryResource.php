<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Tenant\Delivery\Enums\ProofOfDeliveryKind;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DeliveryStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivery as staff see it: which shipment, which driver, where it is, and how it ended.
 *
 * @property Delivery $resource
 */
final class DeliveryResource extends JsonResource
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

        return [
            'id' => $delivery->public_id,
            'shipment' => ['id' => $delivery->shipment->public_id],
            'order' => ['id' => $delivery->shipment->order->public_id, 'number' => $delivery->shipment->order->number],
            /** Its name is null once the driver's personal data is erased. */
            'driver' => ['id' => $delivery->driver->public_id, 'name' => $delivery->driver->name],
            /** 1 for the first delivery of the shipment, 2 for the next, and so on. */
            'attempt' => $delivery->attempt,
            /** "assigned", "picked_up", "on_the_way", "delivered", "failed" or "cancelled". */
            'status' => $delivery->status->value,
            /** Whether the driver has the goods: picked up, on the way, or failed and not yet resolved. */
            'holds_goods' => $delivery->holdsGoods(),
            /** "recipient_not_available", "address_not_found", "refused", "unsafe", "recipient_asked_to_reschedule" or "other"; null unless it failed. */
            'failure_reason' => $delivery->failure_reason?->value,
            'failure_note' => $delivery->failure_note,
            /** Who took the goods; null unless delivered. */
            'recipient_name' => $delivery->recipient_name,
            /** Which proof of delivery it keeps; fetch each from its proof endpoint. Both false unless delivered, and again once the files are purged. */
            'proof' => [
                'photo' => $delivery->proof(ProofOfDeliveryKind::Photo) !== null,
                'signature' => $delivery->proof(ProofOfDeliveryKind::Signature) !== null,
            ],
            /** How a failed delivery was resolved: "retried" or "returned"; null until then. */
            'resolution' => $delivery->resolution?->value,
            'assigned_at' => $delivery->assigned_at->toIso8601String(),
            'picked_up_at' => $delivery->picked_up_at?->toIso8601String(),
            'on_the_way_at' => $delivery->on_the_way_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'cancelled_at' => $delivery->cancelled_at?->toIso8601String(),
            'resolved_at' => $delivery->resolved_at?->toIso8601String(),
            /**
             * Every change of its status, oldest first; only when showing one delivery.
             *
             * @var list<array{from: string|null, to: string, by_type: string|null, by_id: string|null, at: string}>
             */
            'status_changes' => $this->whenLoaded('statusChanges', static fn (): array => array_values($delivery->statusChanges->map(static fn (DeliveryStatusChange $change): array => [
                'from' => $change->from_status?->value,
                'to' => $change->to_status->value,
                'by_type' => $change->changed_by_type,
                'by_id' => $change->changed_by_id,
                'at' => $change->created_at->toIso8601String(),
            ])->all())),
        ];
    }
}
