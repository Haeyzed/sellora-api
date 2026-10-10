<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Inventory\Enums\StockMovementType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One change to a stock level, such as "received 20" or "adjusted -2: damaged", with who or what caused it.
 *
 * Never changed or deleted once written: a database trigger refuses it, and
 * so does this model. A mistake is corrected by a new movement.
 *
 * @property int $id
 * @property string $public_id
 * @property int $stock_item_id
 * @property int $product_variant_id
 * @property int $stock_location_id
 * @property StockMovementType $type
 * @property string|null $reason Why, for adjustments: an AdjustmentReason value.
 * @property string|null $note Free text from staff.
 * @property int $on_hand_delta
 * @property int $reserved_delta
 * @property int $on_hand_after
 * @property int $reserved_after
 * @property string|null $causer_type The account type who caused it, such as "staff_member"; null for the system.
 * @property string|null $causer_id That account's public ID.
 * @property string|null $source_type What it belongs to, such as a reservation or an order, by morph name.
 * @property string|null $source_id That record's public ID.
 * @property CarbonImmutable|null $created_at
 * @property-read StockLocation $location
 */
final class StockMovement extends Model
{
    use HasPublicId;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Stock movements are append-only.'));
        self::deleting(static fn (): never => throw new LogicException('Stock movements are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_item_id' => 'integer',
            'product_variant_id' => 'integer',
            'stock_location_id' => 'integer',
            'type' => StockMovementType::class,
            'on_hand_delta' => 'integer',
            'reserved_delta' => 'integer',
            'on_hand_after' => 'integer',
            'reserved_after' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
