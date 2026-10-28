<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Tenant\Delivery\Models\DriverLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where a driver was at one moment of a delivery under way.
 *
 * @property DriverLocation $resource
 */
final class DriverLocationResource extends JsonResource
{
    public function __construct(DriverLocation $location)
    {
        parent::__construct($location);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $location = $this->resource;

        return [
            /** Degrees (WGS 84). */
            'latitude' => (float) $location->latitude,
            /** Degrees (WGS 84). */
            'longitude' => (float) $location->longitude,
            /** How far off the point may be, in metres; null when the phone didn't say. */
            'accuracy_meters' => $location->accuracy_meters,
            'recorded_at' => $location->created_at->toIso8601String(),
        ];
    }
}
