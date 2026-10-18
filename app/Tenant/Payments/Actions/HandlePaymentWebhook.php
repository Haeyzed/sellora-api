<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Enums\GatewayWebhookKind;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Events\PaymentDisputed;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use App\Tenant\Payments\Services\PaymentEventLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Acts on a recorded payment webhook. It only says "go and ask": a payment it names is verified with the gateway, server-side, and nothing in the webhook itself is trusted.
 *
 * A dispute is recorded on the payment and staff are told; no money state
 * changes (disputes are handled later). Refund webhooks are acted on once
 * refunds exist (Step 15.5). A webhook naming a payment that isn't ours
 * (another app on the same gateway account) is handled by doing nothing.
 */
final readonly class HandlePaymentWebhook
{
    public function __construct(
        private VerifyPayment $verifyPayment,
        private PaymentEventLog $paymentEventLog,
    ) {}

    public function handle(PaymentWebhookEvent $event): void
    {
        if ($event->processed_at !== null) {
            return;
        }

        $payment = $this->paymentNamedBy($event);

        match ($event->kind) {
            GatewayWebhookKind::Payment => $payment === null ? null : $this->verifyPayment->handle($payment),
            GatewayWebhookKind::Dispute => $this->reportDispute($event, $payment),
            GatewayWebhookKind::Refund, GatewayWebhookKind::Ignored => null,
        };

        $event->forceFill(['processed_at' => CarbonImmutable::now()])->save();
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
