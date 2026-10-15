<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Services;

use App\Tenant\Shipping\Exceptions\RestOfWorldZoneExistsException;
use App\Tenant\Shipping\Exceptions\ShippingZoneNameTakenException;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Saves a zone, telling apart the two things the database keeps unique: its name, and covering the rest of the world.
 */
final class ShippingZoneSaving
{
    /**
     * @throws ShippingZoneNameTakenException When another zone has the name, ignoring case.
     * @throws RestOfWorldZoneExistsException When another zone already covers the rest of the world.
     */
    public static function save(ShippingZone $zone): void
    {
        try {
            $zone->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw str_contains($exception->getMessage(), 'shipping_zones_one_rest_of_world')
                ? new RestOfWorldZoneExistsException(previous: $exception)
                : new ShippingZoneNameTakenException(previous: $exception);
        }
    }
}
