<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Features\Features;
use App\Tenant\Payments\Enums\ConnectionStatus;
use App\Tenant\Payments\Models\PaymentGatewayConnection;

/**
 * Which of the store's gateways may take a new payment: connected and enabled, on the store's plan, taking the order's currency from the buyer's country, and in the order's mode (test or live). In the store's own order.
 *
 * Only starting new payments is gated by the plan. Money already in motion
 * never asks here.
 */
final readonly class PaymentGatewayResolver
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private Features $features,
    ) {}

    /**
     * @param  string|null  $method  How the buyer wants to pay on the gateway's page, such as "card"; null for any way.
     * @return list<PaymentGatewayConnection>
     */
    public function forNewPayment(string $currency, ?string $buyerCountry, bool $isTest, ?string $method = null): array
    {
        $connections = PaymentGatewayConnection::query()
            ->where('status', ConnectionStatus::Connected->value)
            ->where('enabled', true)
            ->orderBy('priority')
            ->orderBy('gateway')
            ->get();

        return array_values($connections->filter(fn (PaymentGatewayConnection $connection): bool => $this->takes($connection, $currency, $buyerCountry, $isTest, $method))->all());
    }

    /**
     * Whether one connection may take a new payment like this.
     */
    public function takes(PaymentGatewayConnection $connection, string $currency, ?string $buyerCountry, bool $isTest, ?string $method = null): bool
    {
        if (! $connection->isConnected() || ! $connection->enabled || $connection->mode?->isTest() !== $isTest || ! $this->registry->has($connection->gateway)) {
            return false;
        }

        $capabilities = $this->registry->get($connection->gateway)->capabilities();

        return $this->features->isEnabled($connection->gateway)
            && $capabilities->takes($currency)
            && $capabilities->servesBuyersIn($buyerCountry)
            && ($method === null || in_array($method, $capabilities->methods, true));
    }
}
