<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use App\Tenant\Returns\Enums\ReturnStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One change of a return's status, and who made it. Only ever added: the database refuses edits and deletions.
 *
 * @property int $id
 * @property int $return_id
 * @property ReturnStatus|null $from_status Null when the return was requested.
 * @property ReturnStatus $to_status
 * @property string|null $changed_by_type Null for a guest or the system.
 * @property string|null $changed_by_id
 * @property CarbonImmutable $created_at
 */
final class ReturnStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_id' => 'integer',
            'from_status' => ReturnStatus::class,
            'to_status' => ReturnStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
