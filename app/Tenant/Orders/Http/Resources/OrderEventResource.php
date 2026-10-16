<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Resources;

use App\Tenant\Orders\Models\OrderEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry in an order's history: what happened, who did it, and what changed (never contact details or addresses).
 *
 * @property OrderEvent $resource
 */
final class OrderEventResource extends JsonResource
{
    public function __construct(OrderEvent $event)
    {
        parent::__construct($event);
    }

    /**
     * @return array{type: string, data: array<string, mixed>|null, actor: array{type: string, id: string}|null, created_at: string}
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            /** Such as "placed", "paid", "cancelled", "contact_corrected" or "shipment_shipped". */
            'type' => $event->type->value,
            /** What changed, such as {"from": "open", "to": "cancelled", "reason": "staff"}; which fields, never their values. */
            'data' => $event->data,
            /** Who did it, by account type and ID; null for the system (a payment, the expiry sweep). */
            'actor' => $event->actor_type === null || $event->actor_id === null ? null : ['type' => $event->actor_type, 'id' => $event->actor_id],
            'created_at' => $event->created_at->toIso8601String(),
        ];
    }
}
