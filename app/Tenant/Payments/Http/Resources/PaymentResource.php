<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentEvent;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment toward an order: what was expected, what arrived, how, and what went back from it. Never card numbers or gateway credentials: at most the card's brand and last four digits, when the gateway gave them.
 *
 * @property Payment $resource
 */
final class PaymentResource extends JsonResource
{
    public function __construct(Payment $payment)
    {
        parent::__construct($payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;
        $refunded = $payment->refunds->filter(static fn (PaymentRefund $part): bool => $part->status === RefundStatus::Succeeded);

        return [
            'id' => $payment->public_id,
            'order' => ['id' => $payment->order->public_id, 'number' => $payment->order->number],
            /** "gateway", "bank_transfer" or "cash_on_delivery". */
            'method' => $payment->method->value,
            /** The gateway's key, such as "paystack"; null for a payment made by hand. */
            'gateway' => $payment->gateway,
            /** Taken with test keys: it only ever pays a test order. */
            'is_test' => $payment->is_test,
            /** "pending", "succeeded", "failed", "needs_review" (money that doesn't match: refund it), "unresolved" (the gateway still has no answer) or "cancelled". */
            'status' => $payment->status->value,
            /** What was expected. */
            'amount' => new MoneyResource($payment->amount),
            /** What arrived; null until it does. */
            'received' => $payment->received === null ? null : new MoneyResource($payment->received),
            /** What went back from it so far, not counting refunds still on their way. */
            'refunded' => new MoneyResource(Money::sum($payment->currency, $refunded->map(static fn (PaymentRefund $part): Money => $part->amount)->all())),
            /** Ours, as the gateway knows it. */
            'reference' => $payment->reference,
            'gateway_reference' => $payment->gateway_reference,
            'failure_code' => $payment->failure_code,
            /** Why it is in review: "amount_mismatch", "currency_mismatch", "mode_mismatch" or "succeeded_after_failing". */
            'review_reason' => $payment->review_reason?->value,
            'card_brand' => $payment->card_brand,
            'card_last4' => $payment->card_last4,
            /** Who recorded a payment made by hand, by account type and ID. */
            'confirmed_by' => $payment->confirmed_by_type === null || $payment->confirmed_by_id === null ? null : ['type' => $payment->confirmed_by_type, 'id' => $payment->confirmed_by_id],
            'succeeded_at' => $payment->succeeded_at?->toIso8601String(),
            'failed_at' => $payment->failed_at?->toIso8601String(),
            'last_checked_at' => $payment->last_checked_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            /**
             * Its history, oldest first; only when showing one payment.
             *
             * @var list<array{type: string, data: array<string, mixed>|null, actor: array{type: string, id: string}|null, created_at: string}>
             */
            'events' => $this->whenLoaded('events', static fn (): array => array_values($payment->events->map(static fn (PaymentEvent $event): array => [
                'type' => $event->type->value,
                'data' => $event->data,
                'actor' => $event->actor_type === null || $event->actor_id === null ? null : ['type' => $event->actor_type, 'id' => $event->actor_id],
                'created_at' => $event->created_at->toIso8601String(),
            ])->all())),
        ];
    }
}
