<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentEvent;

/**
 * Writes a payment's history. Entries hold codes and amounts, never card details or gateway payloads.
 */
final readonly class PaymentEventLog
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Payment $payment, PaymentEventType $type, array $data = [], ?AccountReference $actor = null): PaymentEvent
    {
        $event = new PaymentEvent;
        $event->forceFill([
            'payment_id' => $payment->id,
            'type' => $type,
            'data' => $data === [] ? null : $data,
            'actor_type' => $actor?->type,
            'actor_id' => $actor?->publicId,
        ])->save();

        return $event;
    }
}
