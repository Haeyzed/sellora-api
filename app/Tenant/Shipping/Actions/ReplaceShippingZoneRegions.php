<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Exceptions\RestOfWorldZoneHasRegionsException;
use App\Tenant\Shipping\Exceptions\ShippingRegionTakenException;
use App\Tenant\Shipping\Models\ShippingZone;
use App\Tenant\Shipping\Models\ShippingZoneRegion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Sets the countries and states a zone covers, replacing its list. Audited as one change, with the lists before and after.
 *
 * A region belongs to at most one zone: one another zone has is refused,
 * naming that zone. The database enforces it too, so two zones taking the
 * same region at the same moment can't both have it.
 */
final readonly class ReplaceShippingZoneRegions
{
    /**
     * @param  list<ShippingRegion>  $regions  Validated, with states as Geography gives them.
     *
     * @throws ShippingRegionTakenException When another zone has one of them.
     * @throws RestOfWorldZoneHasRegionsException When the zone covers the rest of the world and so lists none.
     */
    public function handle(ShippingZone $zone, array $regions): ShippingZone
    {
        $regions = array_values(array_unique($regions, SORT_REGULAR));

        try {
            DB::transaction(static function () use ($zone, $regions): void {
                $locked = ShippingZone::query()->whereKey($zone->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->covers_rest_of_world && $regions !== []) {
                    throw new RestOfWorldZoneHasRegionsException;
                }

                self::ensureFree($zone, $regions);
                $before = self::labels($locked->regionList());

                $zone->regions()->delete();
                $zone->regions()->createMany(array_map(static fn (ShippingRegion $region): array => ['country_code' => $region->countryCode, 'state_code' => $region->stateCode], $regions));
                $zone->unsetRelation('regions');

                $zone->auditRegionsReplaced($before, self::labels($zone->regionList()));
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new ShippingRegionTakenException(previous: $exception);
        }

        return $zone;
    }

    /**
     * @param  list<ShippingRegion>  $regions
     *
     * @throws ShippingRegionTakenException
     */
    private static function ensureFree(ShippingZone $zone, array $regions): void
    {
        if ($regions === []) {
            return;
        }

        $taken = ShippingZoneRegion::query()
            ->with('zone')
            ->where('shipping_zone_id', '!=', $zone->id)
            ->where(static function ($query) use ($regions): void {
                foreach ($regions as $region) {
                    $query->orWhere(static fn ($match) => $match->where('country_code', $region->countryCode)->where('state_code', $region->stateCode));
                }
            })
            ->orderBy('country_code')
            ->orderByRaw('state_code nulls first')
            ->first();

        if ($taken !== null) {
            throw new ShippingRegionTakenException((new ShippingRegion($taken->country_code, $taken->state_code))->label(), $taken->zone->name);
        }
    }

    /**
     * @param  list<ShippingRegion>  $regions
     * @return list<string>
     */
    private static function labels(array $regions): array
    {
        return array_map(static fn (ShippingRegion $region): string => $region->label(), $regions);
    }
}
