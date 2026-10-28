<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Delivery\Models\DriverLocation;

/**
 * Where the driver was during their deliveries, until it is purged. Erasing the driver deletes it.
 */
final readonly class DriverLocationData implements PersonalDataHandler
{
    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $driver = DriverDataSubject::driver($subject);

        if ($driver === null) {
            return [];
        }

        return array_values(DriverLocation::query()->with('delivery')->where('driver_id', $driver->id)->orderBy('id')->get()->map(static fn (DriverLocation $location): array => [
            'delivery' => $location->delivery->public_id,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy_meters' => $location->accuracy_meters,
            'recorded_at' => $location->created_at->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        $driver = DriverDataSubject::driver($subject);

        if ($driver !== null) {
            DriverLocation::query()->where('driver_id', $driver->id)->delete();
        }
    }
}
