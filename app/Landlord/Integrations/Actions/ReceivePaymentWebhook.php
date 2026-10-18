<?php

declare(strict_types=1);

namespace App\Landlord\Integrations\Actions;

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Integrations\Contracts\PaymentWebhooks;
use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Shared\Integrations\WebhookReceipt;
use SensitiveParameter;

/**
 * Hands a payment gateway's webhook to the store its endpoint ID leads to (CLAUDE.md section 9): the endpoint is looked up, the store's tenancy initialized, and the store checks the signature with its own keys before anything in the webhook is parsed, stored or acted on.
 *
 * The endpoint ID is random and is the only part of the URL that names the
 * store. A store that doesn't serve requests (closed, purging) receives no
 * webhooks; a suspended one still does, so payments already under way finish.
 */
final readonly class ReceivePaymentWebhook
{
    public function __construct(
        private WebhookEndpoints $webhookEndpoints,
        private PaymentWebhooks $paymentWebhooks,
    ) {}

    /**
     * @param  array<string, string>  $headers  By lower-case name, first value only.
     * @return WebhookReceipt|null Null when the endpoint leads to no store.
     */
    public function handle(string $endpointId, #[SensitiveParameter] string $body, array $headers): ?WebhookReceipt
    {
        $target = $this->webhookEndpoints->find($endpointId);
        $store = $target === null ? null : Tenant::query()->find($target->tenantId);

        if ($store === null) {
            return null;
        }

        return $store->run(fn (): WebhookReceipt => $this->paymentWebhooks->receive($endpointId, $body, $headers));
    }
}
