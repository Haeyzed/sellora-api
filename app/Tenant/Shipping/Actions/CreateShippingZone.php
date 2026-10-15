<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Exceptions\RestOfWorldZoneExistsException;
use App\Tenant\Shipping\Exceptions\RestOfWorldZoneHasRegionsException;
use App\Tenant\Shipping\Exceptions\ShippingRegionTakenException;
use App\Tenant\Shipping\Exceptions\ShippingZoneNameTakenException;
use App\Tenant\Shipping\Models\ShippingZone;
use App\Tenant\Shipping\Services\ShippingZoneSaving;
use Illuminate\Support\Facades\DB;

/**
 * Adds a shipping zone, with the countries and states it covers, or covering the rest of the world. Audited.
 */
final readonly class CreateShippingZone
{
    public function __construct(
        private ReplaceShippingZoneRegions $replaceRegions,
    ) {}

    /**
     * @param  list<ShippingRegion>  $regions  Validated, with states as Geography gives them.
     *
     * @throws ShippingZoneNameTakenException
     * @throws RestOfWorldZoneExistsException When another zone already covers the rest of the world.
     * @throws RestOfWorldZoneHasRegionsException When it would cover the rest of the world and list regions too.
     * @throws ShippingRegionTakenException When another zone has one of the regions.
     */
    public function handle(string $name, bool $isActive, bool $coversRestOfWorld, array $regions): ShippingZone
    {
        if ($coversRestOfWorld && $regions !== []) {
            throw new RestOfWorldZoneHasRegionsException;
        }

        $replaceRegions = $this->replaceRegions;

        return DB::transaction(static function () use ($name, $isActive, $coversRestOfWorld, $regions, $replaceRegions): ShippingZone {
            $zone = new ShippingZone(['name' => $name, 'is_active' => $isActive, 'covers_rest_of_world' => $coversRestOfWorld]);
            ShippingZoneSaving::save($zone);

            return $regions === [] ? $zone : $replaceRegions->handle($zone, $regions);
        });
    }
}
