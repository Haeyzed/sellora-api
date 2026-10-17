<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Features\Features;
use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Contracts\PaymentGateway;
use App\Tenant\Payments\Data\CredentialCheck;
use App\Tenant\Payments\Data\GatewayCredentials;
use App\Tenant\Payments\Enums\ConnectionStatus;
use App\Tenant\Payments\Events\PaymentGatewayConnected;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayAccountChangedException;
use App\Tenant\Payments\Exceptions\PaymentGatewayCredentialsInvalidException;
use App\Tenant\Payments\Exceptions\PaymentGatewayModeChangedException;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\MoneyInMotion;
use App\Tenant\Payments\Services\PaymentActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * Connects the store's own account with a gateway, or replaces its credentials, once the gateway confirms they work.
 *
 * Connecting a gateway the store isn't connected to needs it on the plan.
 * Replacing the credentials of one already connected is always allowed,
 * whatever the plan, so money already in motion can still be looked after,
 * but while it is, only with keys of the same account and mode.
 *
 * Its webhook endpoint is created on the central database first, then the
 * store saves the connection naming it: an endpoint left behind by a save
 * that failed leads to no connection, so a webhook to it is an ordinary 404,
 * and it is pruned.
 */
final readonly class ConnectPaymentGateway
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private Features $features,
        private WebhookEndpoints $webhookEndpoints,
        private PaymentActivity $paymentActivity,
        private MoneyInMotion $moneyInMotion,
    ) {}

    /**
     * @param  array<string, string>  $credentials  By field name, already validated against the gateway's credential fields.
     */
    public function handle(StaffMember $actor, string $gatewayKey, #[SensitiveParameter] array $credentials): PaymentGatewayConnection
    {
        $gateway = $this->registry->get($gatewayKey);
        $existing = PaymentGatewayConnection::query()->where('gateway', $gatewayKey)->first();
        $isReplacing = $existing?->isConnected() === true;

        if (! $isReplacing) {
            $this->features->ensureEnabled($gatewayKey);
        }

        $check = $this->checkWithGateway($gateway, $credentials);

        if ($isReplacing) {
            $this->ensureMoneyInMotionStaysPut($existing, $check);
        }
        $endpointId = $existing->endpoint_id ?? $this->webhookEndpoints->create((string) tenant()?->getTenantKey(), $gatewayKey);

        $connection = DB::transaction(function () use ($existing, $gatewayKey, $credentials, $check, $endpointId, $isReplacing): PaymentGatewayConnection {
            $connection = $existing === null ? new PaymentGatewayConnection : PaymentGatewayConnection::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now();

            $connection->forceFill([
                'gateway' => $gatewayKey,
                'status' => ConnectionStatus::Connected,
                'mode' => $check->mode,
                'credentials' => $credentials,
                'credentials_hint' => $check->hint,
                'account_id' => $check->accountId,
                'endpoint_id' => $endpointId,
                'connected_at' => $isReplacing ? $connection->connected_at : $now,
                'checked_at' => $now,
                'disconnected_at' => null,
            ]);

            if (! $isReplacing) {
                $connection->enabled = true;
            }

            $connection->save();

            return $connection;
        });

        $this->paymentActivity->record($actor, $connection, $isReplacing ? 'payment_gateway_credentials_replaced' : 'payment_gateway_connected', $isReplacing ? 'Replaced the credentials of the payment gateway '.$gatewayKey : 'Connected the payment gateway '.$gatewayKey);
        PaymentGatewayConnected::dispatch($connection);

        return $connection;
    }

    /**
     * Payments and refunds still in motion must finish with the account and mode that took them: replacement keys may rotate them, never move them.
     *
     * @throws PaymentGatewayAccountChangedException
     * @throws PaymentGatewayModeChangedException
     */
    private function ensureMoneyInMotionStaysPut(PaymentGatewayConnection $existing, CredentialCheck $check): void
    {
        if (! $this->moneyInMotion->on($existing)) {
            return;
        }

        if ($check->mode !== $existing->mode) {
            throw new PaymentGatewayModeChangedException;
        }

        if ($check->accountId !== null && $existing->account_id !== null && $check->accountId !== $existing->account_id) {
            throw new PaymentGatewayAccountChangedException;
        }
    }

    /**
     * @param  array<string, string>  $credentials
     *
     * @throws PaymentGatewayCredentialsInvalidException
     * @throws PaymentGatewayUnreachableException
     */
    private function checkWithGateway(PaymentGateway $gateway, #[SensitiveParameter] array $credentials): CredentialCheck
    {
        try {
            $check = $gateway->checkCredentials(new GatewayCredentials($credentials));
        } catch (GatewayUnavailableException) {
            throw new PaymentGatewayUnreachableException;
        }

        if (! $check->isValid) {
            throw new PaymentGatewayCredentialsInvalidException;
        }

        return $check;
    }
}
