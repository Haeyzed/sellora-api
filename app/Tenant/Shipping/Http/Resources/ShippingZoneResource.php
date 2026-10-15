<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Resources;

use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping zone and the countries and states it covers.
 *
 * @property ShippingZone $resource
 */
final class ShippingZoneResource extends JsonResource
{
    public function __construct(ShippingZone $zone)
    {
        parent::__construct($zone);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $zone = $this->resource;

        return [
            'id' => $zone->public_id,
            'name' => $zone->name,
            'is_active' => $zone->is_active,
            /** Covers every country no zone lists; such a zone lists no regions. */
            'covers_rest_of_world' => $zone->covers_rest_of_world,
            /**
             * Sorted by country, the whole country before its states. A state's zone beats its country's.
             *
             * @var list<array{country_code: string, state_code: string|null}>
             */
            'regions' => array_map(static fn (ShippingRegion $region): array => ['country_code' => $region->countryCode, 'state_code' => $region->stateCode], $zone->regionList()),
        ];
    }
}
