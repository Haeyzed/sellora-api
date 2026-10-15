<?php

declare(strict_types=1);

namespace App\Tenant\Promotions;

use App\Shared\Features\Features;
use App\Tenant\Promotions\Contracts\DiscountSource;
use Illuminate\Contracts\Container\Container;

/**
 * Every source of discounts: Promotions' own, and those modules register, each used only while its module is enabled for the store.
 */
final class DiscountSourceRegistry
{
    /**
     * @var array<class-string<DiscountSource>, string|null> Each source, with the feature key that must be enabled; null for core sources.
     */
    private array $sources = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<DiscountSource>  $source
     * @param  string|null  $featureKey  The module or integration it belongs to; null for core.
     */
    public function register(string $source, ?string $featureKey = null): void
    {
        $this->sources[$source] = $featureKey;
    }

    /**
     * @return list<DiscountSource> The sources usable in the current store now.
     */
    public function usable(): array
    {
        $features = $this->container->make(Features::class);
        $usable = [];

        foreach ($this->sources as $source => $featureKey) {
            if ($featureKey === null || $features->isEnabled($featureKey)) {
                $usable[] = $this->container->make($source);
            }
        }

        return $usable;
    }
}
