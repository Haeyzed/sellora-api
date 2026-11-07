<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Messaging\Enums\StaffAlertKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Something that needs a staff member's attention, raised from a domain event: listed for the staff holding its kind's permission, and emailed to them. Holds no personal data.
 *
 * @property int $id
 * @property string $public_id
 * @property StaffAlertKind $kind
 * @property string $repeat_key Its kind, what it is about and, for kinds that may come again, the day.
 * @property string $subject_type What it is about, such as "payment".
 * @property string $subject_id The public ID (or a gateway's key) of what it is about.
 * @property array<string, int|string|bool|null> $details What its text shows: order numbers, amounts in minor units with their currency, SKUs.
 * @property CarbonImmutable|null $emailed_at Null when it was not emailed.
 * @property CarbonImmutable $created_at
 */
final class StaffAlert extends Model
{
    use HasPublicId;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'repeat_key', 'subject_type', 'subject_id', 'details'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StaffAlertKind::class,
            'details' => 'array',
            'emailed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
