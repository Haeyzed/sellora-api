<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Orders\Models\OrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Units of an order line given back in a refund, at what the buyer paid for them after discounts, tax included. Written once, never changed or deleted.
 *
 * @property int $id
 * @property int $refund_id
 * @property int $order_line_id
 * @property int $quantity
 * @property string $currency
 * @property Money $amount Tax included.
 * @property Money $tax
 * @property CarbonImmutable $created_at
 * @property-read Refund $refund
 * @property-read OrderLine $orderLine
 */
final class RefundLine extends Model
{
    public const UPDATED_AT = null;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
