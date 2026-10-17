<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Enums\ReviewReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One payment toward an order: what was expected, what arrived, how and when (section 3.3). A financial record: the database refuses changes to what it was for, refuses deleting it, and only lets its status move forward.
 *
 * Only a succeeded payment counts toward its order, at what was received.
 * An order may have several: a bank transfer short of the total and the
 * rest, and later gift cards.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property PaymentMethod $method
 * @property string|null $gateway The gateway's key, for a gateway payment.
 * @property int|null $connection_id
 * @property bool $is_test
 * @property PaymentStatus $status
 * @property string $currency The order's.
 * @property Money $amount What was expected.
 * @property Money|null $received What arrived; set once.
 * @property string $reference Ours: the gateway's reference for it and its idempotency key.
 * @property string|null $idempotency_key The client's.
 * @property string|null $gateway_reference
 * @property string|null $redirect_url The gateway's hosted page for this attempt.
 * @property string|null $failure_code
 * @property ReviewReason|null $review_reason
 * @property string|null $card_brand Only if the gateway gives it.
 * @property string|null $card_last4 Only if the gateway gives it.
 * @property string|null $confirmed_by_type Who confirmed a payment made by hand, by morph name.
 * @property string|null $confirmed_by_id
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $needs_review_at
 * @property CarbonImmutable|null $unresolved_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $last_checked_at When the gateway was last asked about it.
 * @property int $checks How often the gateway was asked about it.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read PaymentGatewayConnection|null $connection
 * @property-read Collection<int, PaymentEvent> $events
 */
final class Payment extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * Where the gateway sends the buyer is not worth auditing; checks happen often.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'redirect_url', 'last_checked_at', 'checks', 'updated_at'];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PaymentGatewayConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConnection::class);
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'is_test' => 'boolean',
            'status' => PaymentStatus::class,
            'amount' => MoneyCast::class.':currency',
            'received' => MoneyCast::class,
            'review_reason' => ReviewReason::class,
            'succeeded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'needs_review_at' => 'immutable_datetime',
            'unresolved_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'checks' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
