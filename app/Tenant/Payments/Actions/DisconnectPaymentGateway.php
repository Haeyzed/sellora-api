<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Enums\ConnectionStatus;
use App\Tenant\Payments\Events\PaymentGatewayDisconnected;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Services\GatewayCredentialsKeeper;
use App\Tenant\Payments\Services\MoneyInMotion;
use App\Tenant\Payments\Services\PaymentActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Disconnects the store's account with a gateway: it is offered for no new payments, and its keys and webhook endpoints are removed. Allowed whatever the plan.
 *
 * While payments or refunds it took are still in motion, its keys and
 * webhook endpoints are kept, so that money is still checked, refunded and
 * told about (section 3.3); they are removed once it has all settled
 * (GatewayCredentialsKeeper, on the hourly sweep).
 *
 * The store removes the connection's keys first, then the central endpoints
 * are deleted: an endpoint left behind by a failed delete leads to no
 * connection, and is pruned.
 */
final readonly class DisconnectPaymentGateway
{
    public function __construct(
        private WebhookEndpoints $webhookEndpoints,
        private PaymentActivity $paymentActivity,
        private MoneyInMotion $moneyInMotion,
        private GatewayCredentialsKeeper $gatewayCredentialsKeeper,
    ) {}

    /**
     * @throws PaymentGatewayNotConnectedException
     */
    public function handle(StaffMember $actor, string $gatewayKey): PaymentGatewayConnection
    {
        [$connection, $endpointIds] = DB::transaction(function () use ($gatewayKey): array {
            $connection = PaymentGatewayConnection::query()->where('gateway', $gatewayKey)->lockForUpdate()->first();

            if ($connection === null || ! $connection->isConnected()) {
                throw new PaymentGatewayNotConnectedException;
            }

            $connection->forceFill([
                'status' => ConnectionStatus::Disconnected,
                'enabled' => false,
                'connected_at' => null,
                'disconnected_at' => CarbonImmutable::now(),
            ]);

            $endpointIds = $this->moneyInMotion->on($connection) ? [] : $this->gatewayCredentialsKeeper->forget($connection);
            $connection->save();

            return [$connection, $endpointIds];
        });

        foreach ($endpointIds as $endpointId) {
            $this->webhookEndpoints->delete($endpointId);
        }

        $this->paymentActivity->record($actor, $connection, 'payment_gateway_disconnected', 'Disconnected the payment gateway '.$gatewayKey);
        PaymentGatewayDisconnected::dispatch($connection);

        return $connection;
    }
}
