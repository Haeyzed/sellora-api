<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Shared\Features\Features;
use App\Tenant\Pricing\Contracts\TaxCalculator;
use Illuminate\Contracts\Container\Container;

/**
 * Chooses the tax calculator for a destination country: a country plug-in while its module or integration is Enabled for the store, else core's rate table (section 3.3).
 *
 * Plug-ins register from their service providers, always, whatever any
 * store's plan; the plan is checked on every calculation, through Features
 * resolved at that moment, never one kept from when the registry was made.
 * Rule-only plug-ins are modules, outside tax services are integrations.
 * When two enabled plug-ins cover one country, the first registered wins.
 */
final class TaxCalculatorRegistry
{
    /**
     * @var list<array{featureKey: string, countryCodes: list<string>, calculator: class-string<TaxCalculator>}>
     */
    private array $plugIns = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Adds a country plug-in, used for the given destination countries while the feature is Enabled.
     *
     * @param  string  $featureKey  The module's or integration's key, such as "india_gst".
     * @param  list<string>  $countryCodes  ISO 3166-1 alpha-2.
     * @param  class-string<TaxCalculator>  $calculator
     */
    public function register(string $featureKey, array $countryCodes, string $calculator): void
    {
        $this->plugIns[] = ['featureKey' => $featureKey, 'countryCodes' => $countryCodes, 'calculator' => $calculator];
    }

    /**
     * The calculator for a destination country in the current store.
     */
    public function for(string $countryCode): TaxCalculator
    {
        foreach ($this->plugIns as $plugIn) {
            if (in_array($countryCode, $plugIn['countryCodes'], true) && $this->container->make(Features::class)->isEnabled($plugIn['featureKey'])) {
                return $this->container->make($plugIn['calculator']);
            }
        }

        return $this->container->make(RateTableTaxCalculator::class);
    }
}
