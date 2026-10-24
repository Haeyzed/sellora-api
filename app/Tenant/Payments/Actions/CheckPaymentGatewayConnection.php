<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Data\CredentialCheck;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\GatewayConnectionAttention;
use Carbon\CarbonImmutable;

/**
 * Asks the gateway whether a connection's credentials still work, such as after the merchant rotated their keys on the gateway's side. Saves when it was checked, and whether it needs the merchant to reconnect it; changes nothing else.
 */
final readonly class CheckPaymentGatewayConnection
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private GatewayConnectionAttention $attention,
    ) {}

    /**
     * @throws PaymentGatewayNotConnectedException
     * @throws PaymentGatewayUnreachableException
     */
    public function handle(string $gatewayKey): CredentialCheck
    {
        $connection = PaymentGatewayConnection::query()->where('gateway', $gatewayKey)->first();

        if ($connection === null || ! $connection->isConnected()) {
            throw new PaymentGatewayNotConnectedException;
        }

        try {
            $check = $this->registry->get($gatewayKey)->checkCredentials($connection->gatewayCredentials());
        } catch (GatewayUnavailableException) {
            throw new PaymentGatewayUnreachableException;
        }

        $connection->forceFill(['checked_at' => CarbonImmutable::now()])->save();
        $check->isValid ? $this->attention->keysWork($connection) : $this->attention->keysRefused($connection);

        return $check;
    }
}
