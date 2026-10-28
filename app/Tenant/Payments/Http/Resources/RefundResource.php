<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\Models\RefundLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What staff gave back on an order, and how each part drawn from a payment stands.
 *
 * @property Refund $resource
 */
final class RefundResource extends JsonResource
{
    public function __construct(Refund $refund)
    {
        parent::__construct($refund);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $refund = $this->resource;

        return [
            'id' => $refund->public_id,
            'order' => ['id' => $refund->order->public_id, 'number' => $refund->order->number],
            /** "pending" while any part is on its way back, "succeeded" when all went back, "failed" when none did, or "partially_failed". */
            'status' => $this->status(),
            /** All of it: lines, shipping, or an amount. */
            'amount' => new MoneyResource($refund->amount),
            /** The part for shipping, its tax included. */
            'shipping' => new MoneyResource($refund->shipping),
            'shipping_tax' => new MoneyResource($refund->shipping_tax),
            /** "reduces_sale" (returned items, shipping, a price reduction; it gets a credit note) or "returns_payment" (money never part of the sale). */
            'purpose' => $refund->purpose,
            'reason' => $refund->reason,
            /** What staff kept back from the lines given back, such as when a return breaks a discount's condition; the lines' amounts are already less this. Zero when nothing was. */
            'withheld' => new MoneyResource($refund->withheld),
            /** Why it was kept back; null when nothing was. */
            'withheld_reason' => $refund->withheld_reason,
            /** Who asked for it, by account type and ID. */
            'requested_by' => ['type' => $refund->requested_by_type, 'id' => $refund->requested_by_id],
            /**
             * Lines given back, each at what the buyer paid for it, tax included, less its share of anything kept back.
             *
             * @var list<array{line: string, quantity: int, amount: MoneyResource, tax: MoneyResource}>
             */
            'lines' => array_values($refund->lines->map(static fn (RefundLine $line): array => [
                'line' => $line->orderLine->public_id,
                'quantity' => $line->quantity,
                'amount' => new MoneyResource($line->amount),
                'tax' => new MoneyResource($line->tax),
            ])->all()),
            /**
             * Each payment it draws on, and how that part stands: through the payment's gateway, or recorded as paid back by hand.
             *
             * A part whose needs_review_at is set waits for staff: the gateway's answers can't tell whether it was made (review_reason: "lookup_failed", "gateway_unclear" or "not_found_after_resend"), so it is never sent again, and it stays pending.
             *
             * @var list<array{id: string, payment: string, status: string, amount: MoneyResource, gateway_reference: string|null, failure_code: string|null, recorded_by: array{type: string, id: string}|null, succeeded_at: string|null, failed_at: string|null, needs_review_at: string|null, review_reason: string|null}>
             */
            'parts' => array_values($refund->paymentRefunds->map(static fn (PaymentRefund $part): array => [
                'id' => $part->public_id,
                'payment' => $part->payment->public_id,
                'status' => $part->status->value,
                'amount' => new MoneyResource($part->amount),
                'gateway_reference' => $part->gateway_reference,
                'failure_code' => $part->failure_code,
                'recorded_by' => $part->recorded_by_type === null || $part->recorded_by_id === null ? null : ['type' => $part->recorded_by_type, 'id' => $part->recorded_by_id],
                'succeeded_at' => $part->succeeded_at?->toIso8601String(),
                'failed_at' => $part->failed_at?->toIso8601String(),
                'needs_review_at' => $part->needs_review_at?->toIso8601String(),
                'review_reason' => $part->review_reason?->value,
            ])->all()),
            'created_at' => $refund->created_at->toIso8601String(),
        ];
    }

    private function status(): string
    {
        $statuses = $this->resource->paymentRefunds->map(static fn (PaymentRefund $part): RefundStatus => $part->status);

        return match (true) {
            $statuses->contains(RefundStatus::Pending) => 'pending',
            $statuses->every(static fn (RefundStatus $status): bool => $status === RefundStatus::Succeeded) => 'succeeded',
            $statuses->every(static fn (RefundStatus $status): bool => $status === RefundStatus::Failed) => 'failed',
            default => 'partially_failed',
        };
    }
}
