<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Cart\Enums\SeenPriceSource;
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
 * @property Money $seen_price The unit price the shopper last saw: a fixed price in its currency, otherwise the base price in the base currency.
 * @property SeenPriceSource $seen_price_source
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
        'seen_price',
        'seen_price_source',
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
            'seen_price' => MoneyCast::class,
            'seen_price_source' => SeenPriceSource::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
