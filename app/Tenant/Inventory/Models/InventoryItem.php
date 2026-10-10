<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Models;

use App\Tenant\Catalog\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * How a variant's stock is handled: whether it is counted at all, whether it may be sold when none is left, and when it counts as running low.
 *
 * A variant without a row behaves as the defaults: counted, no backorders,
 * the store's low-stock threshold. Changes are audited (section 2.1); the
 * stock levels themselves are recorded by the movement ledger instead.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property bool $tracks_stock False for things never counted, such as made-to-order items: always available.
 * @property bool $allows_backorder Whether it may still be ordered when none is available.
 * @property int|null $low_stock_threshold Null to use the store's threshold.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductVariant $variant
 */
final class InventoryItem extends Model implements AuditableContract
{
    use Auditable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tracks_stock' => true,
        'allows_backorder' => false,
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id'];

    /**
     * The variant it describes, even when the variant is in the trash.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'tracks_stock' => 'boolean',
            'allows_backorder' => 'boolean',
            'low_stock_threshold' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
