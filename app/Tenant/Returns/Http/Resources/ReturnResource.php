<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Resources;

use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Models\ReturnRefund;
use App\Tenant\Returns\Models\ReturnStatusChange;
use App\Tenant\Returns\Services\ReturnPhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A return as staff see it: its order, why, its lines and what became of them, who asked, and why staff went past what a buyer could ask for.
 *
 * @mixin OrderReturn
 */
final class ReturnResource extends JsonResource
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
            /** "refund"; exchanges come in Step 18b. */
            'resolution' => $this->resolution,
            /** Why, by code and in the current language. */
            'reason' => ['code' => $this->reason->value, 'label' => $this->reason->label()],
            /** What the buyer or staff added; null once the order was anonymised. */
            'note' => $this->note,
            /** A test order's return: never a sale reduction in reports. */
            'is_test' => $this->is_test,
            /** Approved without the items coming back. */
            'keep_items' => $this->keep_items,
            /** Why staff started it after the window closed. */
            'outside_window_reason' => $this->outside_window_reason,
            /** Why staff started it for units shipped but never marked delivered. */
            'undelivered_reason' => $this->undelivered_reason,
            'rejection_reason' => $this->rejection_reason,
            /** Where its units were inspected; null until then. */
            'stock_location' => $this->stockLocation?->public_id,
            /** Who asked, by account type and ID; null for a guest with the order's access token. */
            'requested_by' => $this->requested_by_type === null || $this->requested_by_id === null ? null : ['type' => $this->requested_by_type, 'id' => $this->requested_by_id],
            /**
             * The units coming back, and once inspected how many went back on the shelf and how many were written off.
             *
             * @var list<array{line: string, product_name: string, variant_name: string|null, sku: string|null, quantity: int, restocked_quantity: int|null, written_off_quantity: int|null}>
             */
            'lines' => array_values($this->lines->map(static fn (ReturnLine $line): array => [
                'line' => $line->orderLine->public_id,
                'product_name' => $line->orderLine->product_name,
                'variant_name' => $line->orderLine->variant_name,
                'sku' => $line->orderLine->sku,
                'quantity' => $line->quantity,
                'restocked_quantity' => $line->restocked_quantity,
                'written_off_quantity' => $line->written_off_quantity,
            ])->all()),
            /** How many photos the buyer added; staff download each by its position from 1. */
            'photos' => $this->getMedia(ReturnPhotos::COLLECTION)->count(),
            'requested_at' => $this->requested_at->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'inspected_at' => $this->inspected_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            /**
             * Each attempt to refund it, oldest first, when showing one return or after refunding it. "started": Payments not yet heard from; "refused": Payments refused it (refusal_code says why); "pending": not gone through yet; "in_review": Payments can't tell whether part went through, and staff must look; "succeeded"; "failed": nothing went back, so it can be tried again; "mixed": part went back and part failed, for staff to settle from the refund.
             *
             * @var list<array{attempt: int, status: string, refusal_code: string|null, refund: string|null, shipping: bool, withheld: int, withheld_reason: string|null, at: string}>
             */
            'refunds' => $this->whenLoaded('refunds', fn (): array => array_values($this->refunds->map(static fn (ReturnRefund $attempt): array => [
                'attempt' => $attempt->attempt,
                'status' => $attempt->status->value,
                'refusal_code' => $attempt->refusal_code,
                'refund' => $attempt->refund?->public_id,
                'shipping' => $attempt->shipping,
                'withheld' => $attempt->withheld_amount,
                'withheld_reason' => $attempt->withheld_reason,
                'at' => $attempt->created_at?->toIso8601String() ?? '',
            ])->all())),
            /**
             * Every change of its status, oldest first, when showing one return.
             *
             * @var list<array{from: string|null, to: string, by: array{type: string, id: string}|null, at: string}>
             */
            'status_changes' => $this->whenLoaded('statusChanges', fn (): array => array_values($this->statusChanges->map(static fn (ReturnStatusChange $change): array => [
                'from' => $change->from_status?->value,
                'to' => $change->to_status->value,
                'by' => $change->changed_by_type === null || $change->changed_by_id === null ? null : ['type' => $change->changed_by_type, 'id' => $change->changed_by_id],
                'at' => $change->created_at->toIso8601String(),
            ])->all())),
        ];
    }
}
