<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A note staff wrote on an order, for each other; never shown to the buyer. Added and deleted, never changed.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property int|null $author_staff_id Null once the author's account is gone.
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property-read StaffMember|null $author
 */
final class OrderNote extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * The body may mention the buyer, so audits keep only that a note was added or deleted.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'body'];

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'author_staff_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'author_staff_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
