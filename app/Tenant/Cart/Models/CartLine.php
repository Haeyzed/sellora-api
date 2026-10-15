<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Catalog\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One variant in a cart and how many the shopper wants.
 *
 * A line stays when its variant stops being for sale (unpublished, unpriced,
 * in the trash or out of stock): the cart shows why, and the shopper decides.
 *
 * @property int $id
 * @property string $public_id
 * @property int $cart_id
 * @property int $product_variant_id
 * @property int $quantity At least 1, at most the cart's per-line limit.
 * @property CarbonImmutable|null $created_at When it was added.
 * @property CarbonImmutable|null $updated_at
 * @property-read Cart $cart
 * @property-read ProductVariant $variant
 */
final class CartLine extends Model
{
    use HasPublicId;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = [
        'product_variant_id',
        'quantity',
    ];

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The variant, even when it is in the trash.
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
            'quantity' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
