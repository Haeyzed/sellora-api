<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Tenant\Payments\Enums\PaymentEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in a payment's history: what happened and who did it. Never changed or deleted (the database refuses it), and never holds card details or gateway payloads.
 *
 * @property int $id
 * @property int $payment_id
 * @property PaymentEventType $type
 * @property array<string, mixed>|null $data
 * @property string|null $actor_type By morph name; null for the system.
 * @property string|null $actor_id
 * @property CarbonImmutable $created_at
 */
final class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentEventType::class,
            'data' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
