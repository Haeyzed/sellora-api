<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use App\Tenant\Orders\Models\OrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many units of one order line a return sends back, and, once inspected, how many went back on the shelf and how many were written off.
 *
 * @property int $id
 * @property int $return_id
 * @property int $order_line_id
 * @property int $quantity
 * @property int|null $restocked_quantity Null until inspected.
 * @property int|null $written_off_quantity Null until inspected.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read OrderReturn $orderReturn
 * @property-read OrderLine $orderLine
 */
final class ReturnLine extends Model
{
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
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
            'return_id' => 'integer',
            'order_line_id' => 'integer',
            'quantity' => 'integer',
            'restocked_quantity' => 'integer',
            'written_off_quantity' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
