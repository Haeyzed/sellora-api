<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a driver was at one moment of a delivery under way. Personal data: purged after the delivery ends.
 *
 * @property int $id
 * @property int $delivery_id
 * @property int $driver_id
 * @property string $latitude Degrees, as a decimal string.
 * @property string $longitude Degrees, as a decimal string.
 * @property int|null $accuracy_meters
 * @property CarbonImmutable $created_at
 * @property-read Delivery $delivery
 */
final class DriverLocation extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Delivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_id' => 'integer',
            'driver_id' => 'integer',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'accuracy_meters' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
