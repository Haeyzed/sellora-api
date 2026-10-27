<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Models;

use App\Tenant\Delivery\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a delivery's status, and who made it. Only ever added: a trigger refuses edits and deletions.
 *
 * @property int $id
 * @property int $delivery_id
 * @property DeliveryStatus|null $from_status Null when it was assigned.
 * @property DeliveryStatus $to_status
 * @property string|null $changed_by_type Null for the system.
 * @property string|null $changed_by_id
 * @property CarbonImmutable $created_at
 * @property-read Delivery $delivery
 */
final class DeliveryStatusChange extends Model
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
            'from_status' => DeliveryStatus::class,
            'to_status' => DeliveryStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
