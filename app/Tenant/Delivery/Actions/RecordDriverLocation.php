<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Tenant\Delivery\Exceptions\DeliveryNotUnderWayException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DriverLocation;
use Illuminate\Support\Facades\DB;

/**
 * Keeps where a delivery's driver is, only while the delivery is picked up or on the way.
 *
 * The delivery is locked for share while its status is checked and the point
 * saved: a status change (which locks it for update) waits until the point
 * is saved, and a point sent while the delivery is being delivered waits and
 * then sees it delivered, so no point is kept after a delivery ends.
 */
final readonly class RecordDriverLocation
{
    /**
     * @throws DeliveryNotUnderWayException
     */
    public function handle(Delivery $delivery, string $latitude, string $longitude, ?int $accuracyMeters): DriverLocation
    {
        return DB::transaction(static function () use ($delivery, $latitude, $longitude, $accuracyMeters): DriverLocation {
            $locked = Delivery::query()->whereKey($delivery->id)->sharedLock()->firstOrFail();

            if (! $locked->isUnderWay()) {
                throw new DeliveryNotUnderWayException;
            }

            $location = new DriverLocation;
            $location->forceFill([
                'delivery_id' => $locked->id,
                'driver_id' => $locked->driver_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => $accuracyMeters,
            ])->save();

            return $location;
        });
    }
}
