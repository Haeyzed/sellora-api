<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Resources;

use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How the store's account with a gateway is connected. Never its credentials: only a hint of which keys these are.
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
     * @return array{id: string, gateway: string, status: string, enabled: bool, priority: int, mode: string|null, credentials_hint: string|null, connected_at: string|null, checked_at: string|null, disconnected_at: string|null}
     */
    public function toArray(Request $request): array
    {
        $connection = $this->resource;

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
            'connected_at' => $connection->connected_at?->toIso8601String(),
            'checked_at' => $connection->checked_at?->toIso8601String(),
            'disconnected_at' => $connection->disconnected_at?->toIso8601String(),
        ];
    }
}
