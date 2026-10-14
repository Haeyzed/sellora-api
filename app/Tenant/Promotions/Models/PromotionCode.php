<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A code customers enter to get a code promotion, such as "WELCOME10". One code per order.
 *
 * Codes are kept as staff wrote them and matched ignoring case; no two codes
 * in a store differ only by case (a database constraint). Codes added one at a
 * time are audited; generated batches are logged as one activity entry.
 *
 * @property int $id
 * @property string $public_id
 * @property int $promotion_id
 * @property string $code 3 to 32 letters, digits, "-" or "_".
 * @property int|null $usage_limit How many orders may use this code; null for no limit of its own.
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Promotion $promotion
 */
final class PromotionCode extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    public const string FORMAT = '/^[A-Za-z0-9_-]{3,32}$/';

    protected $fillable = [
        'code',
        'usage_limit',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
