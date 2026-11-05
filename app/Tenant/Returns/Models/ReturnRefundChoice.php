<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Where the customer asked for a return's refund to go: chosen by them, or recorded by staff at their request with a note saying how they asked. Only ever added to; the latest choice counts.
 *
 * @property int $id
 * @property int $return_id
 * @property string $destination "original_payment" or a module's destination key.
 * @property string $chosen_by_type By morph name.
 * @property string $chosen_by_id
 * @property string|null $note How the customer asked, when staff recorded it; personal data.
 * @property CarbonImmutable|null $anonymised_at
 * @property CarbonImmutable $created_at
 * @property-read OrderReturn $orderReturn
 */
final class ReturnRefundChoice extends Model implements AuditableContract
{
    use Auditable;

    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * The note is personal data that anonymisation clears, so it never goes into the audit trail.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'note'];

    /**
     * Whether staff recorded it for the customer, rather than the customer choosing it.
     */
    public function isRecordedByStaff(): bool
    {
        return $this->chosen_by_type === 'staff_member';
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_id' => 'integer',
            'anonymised_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
