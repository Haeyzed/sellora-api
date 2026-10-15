<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Services;

use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Models\ShippingZone;
use App\Tenant\Shipping\Models\ShippingZoneRegion;

/**
 * Finds the zone that covers a destination: the zone listing its state, else the one listing its whole country, else the rest-of-the-world zone.
 *
 * The most specific region decides, whether or not its zone is active: an
 * inactive zone's regions get no shipping, rather than quietly falling
 * through to a broader zone at other prices. No zone means no shipping
 * there, never a guess.
 */
final readonly class ShippingZones
{
    /**
     * The zone shipping to the destination, or null when none does (no zone covers it, or the one that does is inactive).
     */
    public function for(ShippingRegion $destination): ?ShippingZone
    {
        $region = ShippingZoneRegion::query()
            ->with('zone')
            ->where('country_code', $destination->countryCode)
            ->where(static fn ($query) => $query->whereNull('state_code')->when($destination->stateCode !== null, static fn ($either) => $either->orWhere('state_code', $destination->stateCode)))
            ->orderByRaw('state_code nulls last')
            ->first();

        $zone = $region !== null ? $region->zone : ShippingZone::query()->where('covers_rest_of_world', true)->first();

        return $zone !== null && $zone->is_active ? $zone : null;
    }
}
