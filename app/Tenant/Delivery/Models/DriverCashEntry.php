<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Delivery\Enums\DriverCashEntryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of cash a driver holds: collected on a delivery, or handed over to staff. Only ever added: a trigger refuses edits and deletions.
 *
 * @property int $id
 * @property string $public_id
 * @property int $driver_id
 * @property DriverCashEntryType $type
 * @property int $cash_amount Always positive, in minor units of cash_currency.
 * @property string $cash_currency
 * @property int|null $delivery_id Collected entries only.
 * @property string|null $payment_public_id Collected entries only.
 * @property string $recorded_by_type
 * @property string $recorded_by_id
 * @property CarbonImmutable $created_at
 * @property-read Driver $driver
 * @property-read Delivery|null $delivery
 */
final class DriverCashEntry extends Model
{
    use HasPublicId;

    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

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
            'driver_id' => 'integer',
            'type' => DriverCashEntryType::class,
            'cash_amount' => 'integer',
            'delivery_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
