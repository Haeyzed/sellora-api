<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Inventory\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock of one variant at one location held for a checkout or an order (the holder), until it leaves the shelf or is given back.
 *
 * A holder has at most one active reservation per variant and location, so
 * reserving again for the same holder changes that reservation instead of
 * adding another. Part of it may be backordered: reserved beyond what was
 * available, for a variant that allows backorders. A checkout's hold expires;
 * an order's doesn't.
 *
 * @property int $id
 * @property string $public_id
 * @property int $product_variant_id
 * @property int $stock_location_id
 * @property string $holder_type The holder's morph name, such as "checkout" or "order".
 * @property string $holder_id The holder's public ID.
 * @property int $quantity
 * @property int $backordered_quantity How much of it was beyond what was available when reserved.
 * @property ReservationStatus $status
 * @property CarbonImmutable|null $expires_at Null for a hold that doesn't run out, such as an order's.
 * @property CarbonImmutable|null $settled_at When it stopped being active.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class StockReservation extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'stock_location_id' => 'integer',
            'quantity' => 'integer',
            'backordered_quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
