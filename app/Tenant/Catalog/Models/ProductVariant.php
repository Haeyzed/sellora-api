<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One version of a product that a customer buys, such as "Linen shirt, blue, size M", with its price, SKU and shipping details.
 *
 * Prices are Money in the store's base currency, which can't change once
 * anything is priced, so every variant's currency is the same. A
 * compare-at price, when set, is higher than the price. Weights are grams
 * and dimensions millimetres; whatever ships has a weight.
 *
 * @property int $id
 * @property string $public_id
 * @property int $product_id
 * @property string|null $sku Unique among variants outside the trash.
 * @property string|null $barcode Such as a GTIN.
 * @property Money $price
 * @property Money|null $compare_at_price The "was" price shown crossed out; always higher than the price.
 * @property string $currency ISO 4217; the store's base currency.
 * @property bool $requires_shipping False for things that never ship, such as a service or a gift card.
 * @property int|null $weight_grams Always set for variants that ship.
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property int $position Its place among the product's variants, from 0.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Product $product
 */
final class ProductVariant extends Model implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'requires_shipping',
        'weight_grams',
        'length_mm',
        'width_mm',
        'height_mm',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * The product it belongs to, even when the product is in the trash.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'price' => MoneyCast::class.':currency',
            'compare_at_price' => MoneyCast::class.':currency',
            'requires_shipping' => 'boolean',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
