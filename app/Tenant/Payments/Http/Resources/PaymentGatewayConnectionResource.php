<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Resources;

use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How the store's account with a gateway is connected, and where the gateway sends its webhooks. Never its credentials: only a hint of which keys these are.
 *
 * @property PaymentGatewayConnection $resource
 */
final class PaymentGatewayConnectionResource extends JsonResource
{
    public function __construct(PaymentGatewayConnection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * @return array{id: string, gateway: string, status: string, enabled: bool, priority: int, mode: string|null, credentials_hint: string|null, webhook_urls: array{test: string|null, live: string|null}, connected_at: string|null, checked_at: string|null, disconnected_at: string|null}
     */
    public function toArray(Request $request): array
    {
        $connection = $this->resource;
        $webhookEndpoints = app(WebhookEndpoints::class);
        $urlOf = static fn (?string $endpointId): ?string => $endpointId === null ? null : $webhookEndpoints->paymentWebhookUrl($endpointId);

        return [
            'id' => $connection->public_id,
            'gateway' => $connection->gateway,
            /** "connected" or "disconnected". */
            'status' => $connection->status->value,
            /** Whether buyers are offered it for new payments. */
            'enabled' => $connection->enabled,
            /** Lower is offered first. */
            'priority' => $connection->priority,
            /** "test" or "live", worked out from the keys. Test payments only ever pay test orders. */
            'mode' => $connection->mode?->value,
            /** Which keys these are, such as the last four characters of the public key; never the keys. */
            'credentials_hint' => $connection->credentials_hint,
            /** Where the gateway sends its webhooks, one URL per mode, to paste into the gateway's dashboard: its test webhook URL and its live one. Null once disconnected and settled. */
            'webhook_urls' => [
                'test' => $urlOf($connection->test_endpoint_id),
                'live' => $urlOf($connection->live_endpoint_id),
            ],
            'connected_at' => $connection->connected_at?->toIso8601String(),
            'checked_at' => $connection->checked_at?->toIso8601String(),
            'disconnected_at' => $connection->disconnected_at?->toIso8601String(),
        ];
    }
}
