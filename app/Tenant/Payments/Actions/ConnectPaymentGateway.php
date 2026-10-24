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
 * but while it is, only with keys of the same account and mode, and the keys
 * they replace are kept as retired ones until it settles (section 3.3).
 *
 * Its webhook endpoints, one per mode, are created on the central database
 * first, then the store saves the connection naming them: an endpoint left
 * behind by a save that failed leads to no connection, so a webhook to it is
 * an ordinary 404, and it is pruned.
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
        // A gateway disconnected while money was in motion still holds its keys: reconnecting it is held to the same.
        $hasMoneyInMotion = $existing !== null && $existing->holdsCredentials() && $this->moneyInMotion->on($existing);

        if ($hasMoneyInMotion) {
            $this->ensureMoneyInMotionStaysPut($existing, $check);
        }

        $tenantId = (string) tenant()?->getTenantKey();
        $testEndpointId = $existing->test_endpoint_id ?? $this->webhookEndpoints->create($tenantId, $gatewayKey);
        $liveEndpointId = $existing->live_endpoint_id ?? $this->webhookEndpoints->create($tenantId, $gatewayKey);

        $connection = DB::transaction(function () use ($existing, $gatewayKey, $credentials, $check, $testEndpointId, $liveEndpointId, $isReplacing): PaymentGatewayConnection {
            $connection = $existing === null ? new PaymentGatewayConnection : PaymentGatewayConnection::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now();

            $connection->forceFill([
                'gateway' => $gatewayKey,
                'status' => ConnectionStatus::Connected,
                'mode' => $check->mode,
                'retired_credentials' => $this->retiredAfterReplacing($connection, $credentials),
                'credentials' => $credentials,
                'credentials_hint' => $check->hint,
                'account_id' => $check->accountId,
                'test_endpoint_id' => $testEndpointId,
                'live_endpoint_id' => $liveEndpointId,
                'connected_at' => $isReplacing ? $connection->connected_at : $now,
                'checked_at' => $now,
                'needs_attention_at' => null,
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
     * The keys to keep beside the new ones: those being replaced, and any retired before, while money they took is in motion; none once it has all settled.
     *
     * Read under the connection's lock, so a payment or refund settling at the same moment is seen either way.
     *
     * @param  array<string, string>  $credentials
     * @return list<array<string, string>>|null
     */
    private function retiredAfterReplacing(PaymentGatewayConnection $connection, #[SensitiveParameter] array $credentials): ?array
    {
        if (! $connection->exists || ! $connection->holdsCredentials() || ! $this->moneyInMotion->on($connection)) {
            return null;
        }

        $retired = $connection->retired_credentials ?? [];

        if ($connection->credentials !== null && $connection->credentials !== $credentials) {
            $retired[] = $connection->credentials;
        }

        return $retired === [] ? null : $retired;
    }

    /**
     * Payments and refunds still in motion must finish with the account and mode that took them: replacement keys may rotate them, never move them.
     *
     * @throws PaymentGatewayAccountChangedException
     * @throws PaymentGatewayModeChangedException
     */
    private function ensureMoneyInMotionStaysPut(PaymentGatewayConnection $existing, CredentialCheck $check): void
    {
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
