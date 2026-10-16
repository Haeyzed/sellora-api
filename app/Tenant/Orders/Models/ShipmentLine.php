<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many of one order line a shipment holds. Never changed or deleted.
 *
 * @property int $id
 * @property int $shipment_id
 * @property int $order_line_id
 * @property int $quantity
 * @property-read OrderLine $orderLine
 */
final class ShipmentLine extends Model
{
    public $timestamps = false;

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
            'shipment_id' => 'integer',
            'order_line_id' => 'integer',
            'quantity' => 'integer',
        ];
    }
}
