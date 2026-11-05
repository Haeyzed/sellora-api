<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Payments\Actions\FindRefundDestinations;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Models\ReturnRefund;
use App\Tenant\Returns\Models\ReturnStatusChange;
use App\Tenant\Returns\Services\ReturnPhotos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

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
            /** "requested", "approved", "rejected", "received", "inspected", "awaiting_exchange" (its exchange's replacement isn't paid yet), "completed" or "cancelled". */
            'status' => $this->status,
            /** "refund", or "exchange": other items sent in place of the returned ones. */
            'resolution' => $this->resolution,
            /** Why, by code and in the current language. */
            'reason' => ['code' => $this->reason->value, 'label' => $this->reason->label()],
            /** Whether staff confirmed the store was at fault, when they inspected the items or approved without them coming back; null until then. */
            'stores_fault' => $this->stores_fault,
            /** Why staff decided otherwise than the reason claimed. */
            'fault_overruled_reason' => $this->fault_overruled_reason,
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
            /** Where its refund goes, as the customer chose (or staff recorded at their request, with how they asked in `note`): "original_payment" unless a choice was made. `chosen_by` and `chosen_at` are null when none was. Covers only what was paid with real money. */
            'refund_destination' => $this->refundDestination(),
            /**
             * Its latest exchange, once confirmed: its replacement order, whether it is waiting to be paid (pending), paid (committed) or gave its credit back (released), and how the returned items settle it. Null when none was confirmed.
             *
             * @var array{id: string, status: string, order: array{id: string, number: int}, credit: MoneyResource, difference: MoneyResource, kept: MoneyResource, shipping_charged: bool, confirmed_at: string}|null
             */
            'exchange' => $this->whenLoaded('exchanges', fn (): ?array => self::exchangeOf($this->resource), null),
            /**
             * The units coming back, and once inspected how many went back on the shelf and how many were written off. For an exchange,
eplacement is the variant sent in their place, as many of it; null for a refund.
             *
             * @var list<array{line: string, product_name: string, variant_name: string|null, sku: string|null, quantity: int, restocked_quantity: int|null, written_off_quantity: int|null, replacement: array{id: string, sku: string|null, product: string}|null}>
             */
            'lines' => array_values($this->lines->map(static fn (ReturnLine $line): array => [
                'line' => $line->orderLine->public_id,
                'product_name' => $line->orderLine->product_name,
                'variant_name' => $line->orderLine->variant_name,
                'sku' => $line->orderLine->sku,
                'quantity' => $line->quantity,
                'restocked_quantity' => $line->restocked_quantity,
                'written_off_quantity' => $line->written_off_quantity,
                'replacement' => self::replacementOf($line),
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
             * @var list<array{attempt: int, status: string, refusal_code: string|null, refund: string|null, destination: string, shipping: bool, withheld: int, withheld_reason: string|null, at: string}>
             */
            'refunds' => $this->whenLoaded('refunds', fn (): array => array_values($this->refunds->map(static fn (ReturnRefund $attempt): array => [
                'attempt' => $attempt->attempt,
                'status' => $attempt->status->value,
                'refusal_code' => $attempt->refusal_code,
                'refund' => $attempt->refund?->public_id,
                'destination' => $attempt->destination,
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

    /**
     * @return array{key: string, label: string, chosen_by: array{type: string, id: string}|null, note: string|null, chosen_at: string|null}
     */
    private function refundDestination(): array
    {
        $choice = $this->latestRefundChoice();
        $key = $choice->destination ?? FindRefundDestinations::ORIGINAL_PAYMENT;

        return [
            'key' => $key,
            'label' => $this->destinationLabel($key),
            'chosen_by' => $choice === null ? null : ['type' => $choice->chosen_by_type, 'id' => $choice->chosen_by_id],
            'note' => $choice?->note,
            'chosen_at' => $choice?->created_at->toIso8601String(),
        ];
    }

    /**
     * Its name in the current language, or its key for a destination no module registers any more.
     */
    private function destinationLabel(string $key): string
    {
        return resolve(FindRefundDestinations::class)->labelOf($key);
    }

    /**
     * The variant an exchange sends in place of a line, as staff see it; null for a refund.
     *
     * @return array{id: string, sku: string|null, product: string}|null
     */
    public static function replacementOf(ReturnLine $line): ?array
    {
        $variant = $line->replacementVariant;

        return $variant === null ? null : ['id' => $variant->public_id, 'sku' => $variant->sku, 'product' => $variant->product->public_id];
    }

    /**
     * A return's latest exchange, as the API shows it; null when none was confirmed.
     *
     * @return array{id: string, status: string, order: array{id: string, number: int}, credit: MoneyResource, difference: MoneyResource, kept: MoneyResource, shipping_charged: bool, confirmed_at: string}|null
     */
    public static function exchangeOf(OrderReturn $return): ?array
    {
        $exchange = $return->exchanges->last();

        return $exchange === null ? null : [
            'id' => $exchange->public_id,
            'status' => $exchange->status->value,
            'order' => ['id' => $exchange->order->public_id, 'number' => $exchange->order->number],
            'credit' => new MoneyResource($exchange->credit),
            'difference' => new MoneyResource($exchange->difference),
            'kept' => new MoneyResource($exchange->kept),
            'shipping_charged' => $exchange->shipping_charged,
            'confirmed_at' => ($exchange->created_at ?? throw new LogicException('A saved exchange has a creation time.'))->toIso8601String(),
        ];
    }
}
