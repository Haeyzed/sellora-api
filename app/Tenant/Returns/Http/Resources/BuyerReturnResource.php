<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Resources;

use App\Tenant\Payments\Actions\FindRefundDestinations;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Services\ReturnPhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A return as its buyer sees it: where it stands, why, and the units coming back. What staff recorded for themselves (who asked, why they went past the window, what became of each unit) stays with staff.
 *
 * @mixin OrderReturn
 */
final class BuyerReturnResource extends JsonResource
{
    public function __construct(OrderReturn $return)
    {
        parent::__construct($return);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            /** Its order's number and its own, such as "#1042-R1". */
            'number' => $this->displayNumber(),
            'order' => ['id' => $this->order->public_id, 'number' => $this->order->number],
            /** "requested", "approved", "rejected", "received", "inspected", "completed" or "cancelled". */
            'status' => $this->status,
            /** Why, by code and in the buyer's language. */
            'reason' => ['code' => $this->reason->value, 'label' => $this->reason->label()],
            'note' => $this->note,
            /** Approved without the items having to come back. */
            'keep_items' => $this->keep_items,
            /** Why the store rejected it. */
            'rejection_reason' => $this->rejection_reason,
            /** Where its refund goes: "original_payment" (back to how the buyer paid) unless they chose otherwise; `chosen_at` is null when no choice was made. Covers only what was paid with real money. */
            'refund_destination' => $this->refundDestination(),
            /**
             * The units coming back.
             *
             * @var list<array{line: string, product_name: string, variant_name: string|null, quantity: int}>
             */
            'lines' => array_values($this->lines->map(static fn (ReturnLine $line): array => [
                'line' => $line->orderLine->public_id,
                'product_name' => $line->orderLine->product_name,
                'variant_name' => $line->orderLine->variant_name,
                'quantity' => $line->quantity,
            ])->all()),
            /** How many photos were added. */
            'photos' => $this->getMedia(ReturnPhotos::COLLECTION)->count(),
            'requested_at' => $this->requested_at->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{key: string, label: string, chosen_at: string|null}
     */
    private function refundDestination(): array
    {
        $choice = $this->latestRefundChoice();
        $key = $choice->destination ?? FindRefundDestinations::ORIGINAL_PAYMENT;

        return ['key' => $key, 'label' => $this->destinationLabel($key), 'chosen_at' => $choice?->created_at->toIso8601String()];
    }

    /**
     * Its name in the buyer's language, or its key for a destination no module registers any more.
     */
    private function destinationLabel(string $key): string
    {
        return resolve(FindRefundDestinations::class)->labelOf($key);
    }
}
