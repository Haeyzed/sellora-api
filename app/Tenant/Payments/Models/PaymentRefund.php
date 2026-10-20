<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Payments\Enums\RefundStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * The part of a refund drawn from one payment, going back the way that payment came: through its gateway, or recorded as paid back by hand. A financial record: what it gives back never changes, its status only moves forward, and it is never deleted.
 *
 * @property int $id
 * @property string $public_id
 * @property int $refund_id
 * @property int $payment_id
 * @property RefundStatus $status
 * @property string $currency
 * @property Money $amount
 * @property string $reference Ours: the gateway's idempotency key for it.
 * @property string|null $gateway_reference
 * @property string|null $failure_code
 * @property string|null $recorded_by_type Who recorded paying it back by hand, by morph name.
 * @property string|null $recorded_by_id
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $last_checked_at When the gateway was last asked about it.
 * @property int $checks How often the gateway was asked about it.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Refund $refund
 * @property-read Payment $payment
 */
final class PaymentRefund extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * Checks happen often.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'last_checked_at', 'checks', 'updated_at'];

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => MoneyCast::class.':currency',
            'succeeded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'checks' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
