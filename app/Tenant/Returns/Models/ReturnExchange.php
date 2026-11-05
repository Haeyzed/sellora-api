<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Returns\Enums\ReturnExchangeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A return's exchange as staff confirmed it (Step 18d): its replacement order, and how the returned items settle it.
 *
 * What the returned units are worth is split three ways: what they credit
 * toward the replacement, what goes back to the buyer (a cheaper
 * replacement's difference), and what the store keeps (what a free
 * like-for-like replacement didn't use).
 *
 * @property int $id
 * @property string $public_id
 * @property int $return_id
 * @property int $order_id The replacement order.
 * @property ReturnExchangeStatus $status
 * @property bool $stores_fault Whether the store was at fault, as confirmed: a like-for-like replacement and its delivery were then free.
 * @property bool $shipping_charged Whether the buyer paid for the replacement's delivery.
 * @property string $currency
 * @property Money $returned_value What the returned units cost the buyer after their discounts.
 * @property Money $credit What the returned items pay toward the replacement.
 * @property Money $difference What goes back to the buyer; zero when none.
 * @property Money $kept What the store keeps; zero when none.
 * @property int|null $refund_id The refund of the original order's returned lines to the exchange, once the replacement is paid.
 * @property string $confirmed_by_type
 * @property string $confirmed_by_id
 * @property CarbonImmutable|null $committed_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read OrderReturn $orderReturn
 * @property-read Order $order
 * @property-read Refund|null $refund
 */
final class ReturnExchange extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'updated_at'];

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /**
     * The replacement order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_id' => 'integer',
            'order_id' => 'integer',
            'status' => ReturnExchangeStatus::class,
            'stores_fault' => 'boolean',
            'shipping_charged' => 'boolean',
            'returned_value' => MoneyCast::class.':currency',
            'credit' => MoneyCast::class.':currency',
            'difference' => MoneyCast::class.':currency',
            'kept' => MoneyCast::class.':currency',
            'refund_id' => 'integer',
            'committed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
