<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Enums\TaxOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thing bought on an order, copied as it was: names in the order's language, SKU, price, discount and tax. Never changed or deleted.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property int $position From 0, in the order the buyer saw them.
 * @property int $product_id
 * @property int $product_variant_id
 * @property string $product_name
 * @property string|null $variant_name Its option values, such as "Blue / M"; null without options.
 * @property string|null $sku
 * @property bool $requires_shipping
 * @property int|null $weight_grams
 * @property string|null $tax_class_id The tax class's public ID.
 * @property string $tax_class_name
 * @property TaxOutcome $tax_outcome
 * @property int $quantity
 * @property string $currency
 * @property Money $unit_price
 * @property bool $price_is_fixed The merchant set this price for the currency; otherwise it was converted.
 * @property Money $subtotal Unit price × quantity.
 * @property Money $discount
 * @property Money $net Before tax, after the discount.
 * @property Money $tax
 * @property Money $total What the buyer paid for it, tax included.
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 * @property-read Product $product
 * @property-read ProductVariant $variant
 * @property-read Collection<int, OrderDiscount> $discounts
 * @property-read Collection<int, OrderTax> $taxes
 */
final class OrderLine extends Model
{
    use HasPublicId;

    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return HasMany<OrderDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(OrderTax::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'position' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'requires_shipping' => 'boolean',
            'weight_grams' => 'integer',
            'tax_outcome' => TaxOutcome::class,
            'quantity' => 'integer',
            'unit_price' => MoneyCast::class.':currency',
            'price_is_fixed' => 'boolean',
            'subtotal' => MoneyCast::class.':currency',
            'discount' => MoneyCast::class.':currency',
            'net' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
