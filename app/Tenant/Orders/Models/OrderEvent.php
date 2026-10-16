<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Tenant\Orders\Enums\OrderEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in an order's history: what happened, who did it, and what changed. Never changed or deleted, and never holds contact details or addresses.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderEventType $type
 * @property array<string, mixed>|null $data Such as {"from": "open", "to": "cancelled"}.
 * @property string|null $actor_type Who did it, by morph name; null for the system.
 * @property string|null $actor_id Their public ID.
 * @property CarbonImmutable $created_at
 */
final class OrderEvent extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'type' => OrderEventType::class,
            'data' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
