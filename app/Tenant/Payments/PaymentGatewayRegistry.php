<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Tenant\Payments\Contracts\PaymentGateway;
use App\Tenant\Payments\Exceptions\UnknownPaymentGatewayException;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Every payment gateway the platform has, as each gateway integration registers itself in its service provider.
 */
final class PaymentGatewayRegistry
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private array $gateways = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<PaymentGateway>  $gatewayClass
     *
     * @throws LogicException When another gateway already uses its key.
     */
    public function register(string $key, string $gatewayClass): void
    {
        if (isset($this->gateways[$key]) && $this->gateways[$key] !== $gatewayClass) {
            throw new LogicException("Two payment gateways use the key \"{$key}\".");
        }

        $this->gateways[$key] = $gatewayClass;
    }

    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    /**
     * @throws UnknownPaymentGatewayException
     */
    public function get(string $key): PaymentGateway
    {
        $gatewayClass = $this->gateways[$key] ?? throw new UnknownPaymentGatewayException;
        $gateway = $this->container->make($gatewayClass);

        if ($gateway->key() !== $key) {
            throw new LogicException("The payment gateway {$gatewayClass} says its key is \"{$gateway->key()}\", but registered as \"{$key}\".");
        }

        return $gateway;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->gateways);
        sort($keys);

        return $keys;
    }
}
