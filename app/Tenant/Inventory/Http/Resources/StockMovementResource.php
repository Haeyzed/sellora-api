<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Resources;

use App\Tenant\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One change to a stock level, with who or what caused it. Never changed once recorded.
 *
 * @property StockMovement $resource
 */
final class StockMovementResource extends JsonResource
{
    public function __construct(StockMovement $movement)
    {
        parent::__construct($movement);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $movement = $this->resource;

        return [
            'id' => $movement->public_id,
            'type' => $movement->type,
            /** Why, for adjustments, such as "damaged". */
            'reason' => $movement->reason,
            'note' => $movement->note,
            'location_id' => $this->whenLoaded('location', static fn (): string => $movement->location->public_id),
            'on_hand_change' => $movement->on_hand_delta,
            'reserved_change' => $movement->reserved_delta,
            'on_hand_after' => $movement->on_hand_after,
            'reserved_after' => $movement->reserved_after,
            /**
             * Who caused it, such as {"type": "staff_member", "id": "01J…"}; null for the system.
             *
             * @var array{type: string, id: string}|null
             */
            'caused_by' => $movement->causer_type === null ? null : ['type' => $movement->causer_type, 'id' => $movement->causer_id],
            /**
             * What it belongs to, such as an order; null when nothing.
             *
             * @var array{type: string, id: string}|null
             */
            'source' => $movement->source_type === null ? null : ['type' => $movement->source_type, 'id' => $movement->source_id],
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
