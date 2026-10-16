<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Enums\ConnectionStatus;
use App\Tenant\Payments\Events\PaymentGatewayDisconnected;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Services\PaymentActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Disconnects the store's account with a gateway: its credentials are removed and its webhook endpoint deleted. Allowed whatever the plan.
 *
 * The store removes the connection first, then the central endpoint is
 * deleted: an endpoint left behind by a failed delete leads to no
 * connection, and is pruned.
 */
final readonly class DisconnectPaymentGateway
{
    public function __construct(
        private WebhookEndpoints $webhookEndpoints,
        private PaymentActivity $paymentActivity,
    ) {}

    /**
     * @throws PaymentGatewayNotConnectedException
     */
    public function handle(StaffMember $actor, string $gatewayKey): PaymentGatewayConnection
    {
        [$connection, $endpointId] = DB::transaction(static function () use ($gatewayKey): array {
            $connection = PaymentGatewayConnection::query()->where('gateway', $gatewayKey)->lockForUpdate()->first();

            if ($connection === null || ! $connection->isConnected()) {
                throw new PaymentGatewayNotConnectedException;
            }

            $endpointId = $connection->endpoint_id;
            $connection->forceFill([
                'status' => ConnectionStatus::Disconnected,
                'enabled' => false,
                'credentials' => null,
                'credentials_hint' => null,
                'endpoint_id' => null,
                'connected_at' => null,
                'disconnected_at' => CarbonImmutable::now(),
            ])->save();

            return [$connection, $endpointId];
        });

        if ($endpointId !== null) {
            $this->webhookEndpoints->delete($endpointId);
        }

        $this->paymentActivity->record($actor, $connection, 'payment_gateway_disconnected', 'Disconnected the payment gateway '.$gatewayKey);
        PaymentGatewayDisconnected::dispatch($connection);

        return $connection;
    }
}
