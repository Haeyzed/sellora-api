<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Enums\GatewayWebhookKind;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Events\PaymentDisputed;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use App\Tenant\Payments\Services\PaymentEventLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Acts on a recorded payment webhook. It only says "go and ask": a payment it names is verified with the gateway, server-side, and nothing in the webhook itself is trusted.
 *
 * A refund it names is verified the same way. A dispute is recorded on the
 * payment and staff are told; no money state changes (disputes are handled
 * later). A webhook naming a payment or refund that isn't ours (another app
 * on the same gateway account) is handled by doing nothing.
 */
final readonly class HandlePaymentWebhook
{
    public function __construct(
        private VerifyPayment $verifyPayment,
        private VerifyRefund $verifyRefund,
        private PaymentEventLog $paymentEventLog,
    ) {}

    public function handle(PaymentWebhookEvent $event): void
    {
        if ($event->processed_at !== null) {
            return;
        }

        match ($event->kind) {
            GatewayWebhookKind::Payment => $this->verifyNamedPayment($event),
            GatewayWebhookKind::Dispute => $this->reportDispute($event, $this->paymentNamedBy($event)),
            GatewayWebhookKind::Refund => $this->verifyNamedRefund($event),
            GatewayWebhookKind::Ignored => null,
        };

        $event->forceFill(['processed_at' => CarbonImmutable::now()])->save();
    }

    private function verifyNamedPayment(PaymentWebhookEvent $event): void
    {
        $payment = $this->paymentNamedBy($event);

        if ($payment !== null) {
            $this->verifyPayment->handle($payment);
        }
    }

    /**
     * The refund it names, or, for a gateway whose refund webhooks name only the payment refunded (Paystack), each of that payment's refunds not in staff review.
     */
    private function verifyNamedRefund(PaymentWebhookEvent $event): void
    {
        $paymentRefund = $event->reference === null ? null : PaymentRefund::query()
            ->where('reference', $event->reference)
            ->whereHas('payment', static fn (Builder $payment) => $payment->where('connection_id', $event->connection_id))
            ->first();

        if ($paymentRefund !== null) {
            $this->verifyRefund->handle($paymentRefund);

            return;
        }

        // A refund that went through is left alone by VerifyRefund; one that failed may still have gone through after all.
        $refunds = $this->paymentNamedBy($event)?->refunds()->whereNull('needs_review_at')->orderBy('id')->get() ?? [];

        foreach ($refunds as $part) {
            $this->verifyRefund->handle($part);
        }
    }

    /**
     * Ours, and taken through the connection the webhook came to.
     */
    private function paymentNamedBy(PaymentWebhookEvent $event): ?Payment
    {
        if ($event->reference === null) {
            return null;
        }

        return Payment::query()->where('reference', $event->reference)->where('connection_id', $event->connection_id)->first();
    }

    private function reportDispute(PaymentWebhookEvent $event, ?Payment $payment): void
    {
        if ($payment !== null) {
            $this->paymentEventLog->record($payment, PaymentEventType::Disputed, ['gateway_event' => $event->event_id]);
        }

        // Only public IDs: staff find the rest from there.
        Log::warning('A buyer disputed a payment with their bank; nothing changes until staff act.', ['payment' => $payment?->public_id, 'gateway' => $event->gateway]);
        PaymentDisputed::dispatch($event->gateway, $payment);
    }
}
