<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a payment method held for an order when it was placed (section 13): a module's amount (such as a gift card's balance), or nothing for core's own methods, which go through the same steps. Committed once the order is paid; released if it expires or is cancelled unpaid; refunded, once its method gave it back, if it is cancelled after it was paid.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property string $method The payment method's key.
 * @property string|null $reference What the method calls its hold, if anything.
 * @property string $currency
 * @property Money $amount What it covers of the order; zero for a method that holds nothing of its own.
 * @property PaymentHoldStatus $status
 * @property CarbonImmutable|null $committed_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $refund_failed_at When giving it back last failed; cleared once it is given back.
 * @property string|null $refund_failure Why giving it back last failed, as a code.
 * @property CarbonImmutable|null $settle_failed_at When committing or releasing it last failed; cleared once it is settled.
 * @property string|null $settle_failure Why committing or releasing it last failed, as a code.
 * @property int $settle_failures How many tries to commit or release it failed in a row.
 * @property CarbonImmutable|null $settle_alerted_at When staff were alerted that it keeps failing; only once.
 * @property CarbonImmutable|null $settle_recovered_at When a retry settled it after it had failed.
 * @property CarbonImmutable|null $order_update_failed_at When recording its order's payments after it settled last failed; cleared once that works.
 * @property string|null $order_update_failure Why recording its order's payments last failed, as a code.
 * @property int $order_update_failures How many tries to record its order's payments after it settled failed in a row.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 */
final class CheckoutPaymentHold extends Model
{
    use HasPublicId;

    protected $fillable = [];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => MoneyCast::class.':currency',
            'status' => PaymentHoldStatus::class,
            'committed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
            'refund_failed_at' => 'immutable_datetime',
            'settle_failed_at' => 'immutable_datetime',
            'settle_failures' => 'integer',
            'settle_alerted_at' => 'immutable_datetime',
            'settle_recovered_at' => 'immutable_datetime',
            'order_update_failed_at' => 'immutable_datetime',
            'order_update_failures' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
