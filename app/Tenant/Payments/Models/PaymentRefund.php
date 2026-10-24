<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Payments\Enums\RefundReviewReason;
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
 * @property string $reference Ours, sent with it: Paystack keeps it as the refund's merchant note.
 * @property string|null $gateway_reference
 * @property string|null $failure_code
 * @property string|null $recorded_by_type Who recorded paying it back by hand, by morph name.
 * @property string|null $recorded_by_id
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $last_checked_at When the gateway was last asked about it.
 * @property int $checks How often the gateway was asked about it.
 * @property CarbonImmutable|null $slow_alerted_at When staff were told it was still pending at the gateway.
 * @property int $send_attempts How often it was claimed for sending to the gateway: once, and again at most once.
 * @property CarbonImmutable|null $not_found_at When the gateway first reported having no such refund, since it was last sent.
 * @property CarbonImmutable|null $needs_review_at When it went to staff review: never sent again from then on.
 * @property RefundReviewReason|null $review_reason
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
    protected $auditExclude = ['id', 'public_id', 'last_checked_at', 'checks', 'not_found_at', 'updated_at'];

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
            'slow_alerted_at' => 'immutable_datetime',
            'send_attempts' => 'integer',
            'not_found_at' => 'immutable_datetime',
            'needs_review_at' => 'immutable_datetime',
            'review_reason' => RefundReviewReason::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
