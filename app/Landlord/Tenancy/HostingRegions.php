<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * The hosting regions Sellora runs in (config platform.regions): their keys, such as "africa", and the real cloud region each maps to.
 */
final readonly class HostingRegions
{
    public function __construct(private ConfigRepository $config) {}

    /**
     * Every region's key, whether or not it has a database server yet.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(strval(...), array_keys($this->config->array('platform.regions')));
    }

    public function exists(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    /**
     * The cloud provider's name for the region, such as "af-south-1", or null when the environment doesn't set it.
     */
    public function cloudRegionOf(string $code): ?string
    {
        $cloudRegion = $this->config->get("platform.regions.{$code}.cloud_region");

        return is_string($cloudRegion) && $cloudRegion !== '' ? $cloudRegion : null;
    }
}
