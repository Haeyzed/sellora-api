<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Tenant\Payments\Enums\GatewayWebhookKind;

/**
 * What a verified webhook is about: which payment or refund to go and ask the gateway about. Its own status is never trusted.
 */
final readonly class GatewayWebhookEvent
{
    /**
     * @param  string  $eventId  The gateway's own ID for the event, so it is handled once however often it is sent.
     * @param  string|null  $reference  Our reference of the payment or refund it is about; null when it names none.
     */
    public function __construct(
        public string $eventId,
        public GatewayWebhookKind $kind,
        public ?string $reference = null,
    ) {}
}
