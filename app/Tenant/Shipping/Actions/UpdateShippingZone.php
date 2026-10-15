<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Tenant\Shipping\Exceptions\RestOfWorldZoneExistsException;
use App\Tenant\Shipping\Exceptions\RestOfWorldZoneHasRegionsException;
use App\Tenant\Shipping\Exceptions\ShippingZoneNameTakenException;
use App\Tenant\Shipping\Models\ShippingZone;
use App\Tenant\Shipping\Services\ShippingZoneSaving;
use Illuminate\Support\Facades\DB;

/**
 * Renames a zone, switches it on or off, or makes it cover the rest of the world (or stop). Audited. Its regions change on their own.
 */
final readonly class UpdateShippingZone
{
    /**
     * @param  string|null  $name  Null to keep it.
     * @param  bool|null  $isActive  Null to keep it.
     * @param  bool|null  $coversRestOfWorld  Null to keep it.
     *
     * @throws ShippingZoneNameTakenException
     * @throws RestOfWorldZoneExistsException When another zone already covers the rest of the world.
     * @throws RestOfWorldZoneHasRegionsException When it would cover the rest of the world while it lists regions.
     */
    public function handle(ShippingZone $zone, ?string $name, ?bool $isActive, ?bool $coversRestOfWorld): ShippingZone
    {
        return DB::transaction(static function () use ($zone, $name, $isActive, $coversRestOfWorld): ShippingZone {
            ShippingZone::query()->whereKey($zone->getKey())->lockForUpdate()->firstOrFail();
            $zone->refresh();

            if ($coversRestOfWorld === true && $zone->regions()->exists()) {
                throw new RestOfWorldZoneHasRegionsException;
            }

            $zone->fill(array_filter(['name' => $name, 'is_active' => $isActive, 'covers_rest_of_world' => $coversRestOfWorld], static fn (mixed $value): bool => $value !== null));
            ShippingZoneSaving::save($zone);

            return $zone;
        });
    }
}
