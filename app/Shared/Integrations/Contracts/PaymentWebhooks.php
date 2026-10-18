<?php

declare(strict_types=1);

namespace App\Shared\Integrations\Contracts;

use App\Shared\Integrations\WebhookReceipt;
use SensitiveParameter;

/**
 * Receives a payment gateway's webhook for the current store, once the platform has found the store from the endpoint ID and initialized its tenancy. Implemented by Tenant\Payments.
 *
 * The signature is checked with the store's own keys before anything in the
 * webhook is parsed, stored or acted on. A webhook that checks out is
 * recorded once and handled on the payments queue; it only says which
 * payment to go and ask the gateway about.
 */
interface PaymentWebhooks
{
    /**
     * @param  string  $body  Exactly as it arrived: the signature covers it.
     * @param  array<string, string>  $headers  By lower-case name, first value only.
     */
    public function receive(string $endpointId, #[SensitiveParameter] string $body, array $headers): WebhookReceipt;
}
