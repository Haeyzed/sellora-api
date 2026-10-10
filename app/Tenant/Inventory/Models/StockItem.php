<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Models;

use App\Tenant\Catalog\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of a variant is at a location: on hand (physically there) and reserved (held for checkouts and orders), in whole units.
 *
 * Available is on hand minus reserved. Neither number is ever below zero.
 * Only the stock ledger (Services\StockLedger) changes them, always together
 * with a stock movement, so every level can be recalculated from its
 * movements.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $stock_location_id
 * @property int $on_hand
 * @property int $reserved
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductVariant $variant
 * @property-read StockLocation $location
 */
final class StockItem extends Model
{
    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function available(): int
    {
        return $this->on_hand - $this->reserved;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'stock_location_id' => 'integer',
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
