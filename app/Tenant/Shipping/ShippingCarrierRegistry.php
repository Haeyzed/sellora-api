<?php

declare(strict_types=1);

namespace App\Tenant\Shipping;

use App\Shared\Features\Features;
use App\Tenant\Shipping\Contracts\ShippingCarrier;
use Illuminate\Contracts\Container\Container;

/**
 * The shipping carriers integrations offer, each usable only while its integration is Enabled for the store (section 8).
 *
 * Integrations register from their service providers, always, whatever any
 * store's plan; the plan is checked on every use, through Features resolved
 * at that moment. Core holds no carrier of its own.
 */
final class ShippingCarrierRegistry
{
    /**
     * @var array<string, array{featureKey: string, carrier: class-string<ShippingCarrier>}> By carrier key.
     */
    private array $carriers = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Adds a carrier, usable while the feature is Enabled.
     *
     * @param  string  $featureKey  The integration's key.
     * @param  class-string<ShippingCarrier>  $carrier
     */
    public function register(string $featureKey, string $carrier): void
    {
        $this->carriers[$this->container->make($carrier)->key()] = ['featureKey' => $featureKey, 'carrier' => $carrier];
    }

    /**
     * The carrier with the key, while it is usable in the current store; null otherwise.
     */
    public function enabled(string $key): ?ShippingCarrier
    {
        $registered = $this->carriers[$key] ?? null;

        if ($registered === null || ! $this->container->make(Features::class)->isEnabled($registered['featureKey'])) {
            return null;
        }

        return $this->container->make($registered['carrier']);
    }
}
