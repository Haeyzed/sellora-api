<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * What of an order is sent together, how, and where it is. Audited: its tracking and every status change.
 *
 * What it holds never changes once created; cancelling it (before it
 * leaves) is how a mistake is undone.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property ShippingFulfilment $fulfilment
 * @property int $stock_location_id Where it is sent from.
 * @property ShipmentStatus $status
 * @property string|null $carrier
 * @property string|null $carrier_service
 * @property string|null $tracking_number
 * @property string|null $tracking_url Always https.
 * @property CarbonImmutable|null $ready_at A pickup's, when it was ready.
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at When it arrived, or was collected.
 * @property CarbonImmutable|null $cancelled_at
 * @property string $created_by_type
 * @property string $created_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read StockLocation $stockLocation
 * @property-read Collection<int, ShipmentLine> $lines
 */
final class Shipment extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'updated_at'];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return HasMany<ShipmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'fulfilment' => ShippingFulfilment::class,
            'stock_location_id' => 'integer',
            'status' => ShipmentStatus::class,
            'ready_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
