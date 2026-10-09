<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Catalog\Services\ProductSearchText;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\ProductVariantFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One version of a product that a customer buys, such as "Linen shirt, blue, size M", with its price, SKU and shipping details.
 *
 * Prices are Money in the store's base currency, which can't change once
 * anything is priced, so every priced variant's currency is the same. A
 * variant can exist before it has a price but can't be bought until it has
 * one. A compare-at price, when set, is higher than the price. Weights are grams
 * and dimensions millimetres; whatever ships has a weight.
 *
 * @property int $id
 * @property string $public_id
 * @property int $product_id
 * @property string|null $sku Unique among variants outside the trash.
 * @property string|null $barcode Such as a GTIN.
 * @property Money|null $price Null until priced; an unpriced variant can't be bought.
 * @property Money|null $compare_at_price The "was" price shown crossed out; always higher than the price, and only on a priced variant.
 * @property string|null $currency ISO 4217; the store's base currency, or null until priced.
 * @property bool $requires_shipping False for things that never ship, such as a service or a gift card.
 * @property int|null $weight_grams Always set for variants that ship.
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property int $position Its place among the product's variants, from 0.
 * @property string $attribute_signature Its attribute and value IDs, such as "3:12;5:40"; empty for the one variant of a product without options. Unique within the product outside the trash.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property int|null $image_media_id One of its product's gallery images.
 * @property bool $trashed_with_product True while it is in the trash because its product is, so restoring the product brings it back.
 * @property-read Product $product
 * @property-read Collection<int, AttributeValue> $attributeValues
 * @property-read Media|null $image
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
     * The signature follows the values, which are audited on their own.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'attribute_signature'];

    /**
     * The product it belongs to, even when the product is in the trash.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Its own image: one of its product's gallery images, or none.
     *
     * @return BelongsTo<Media, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    /**
     * Its value for each of the product's options, such as "Blue" and "M".
     *
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class)->withPivot('attribute_id')->orderByPivot('attribute_id');
    }

    /**
     * Keeps its product's search text current when a variant comes or goes, or its SKU or barcode changes.
     */
    protected static function booted(): void
    {
        $refresh = static function (self $variant): void {
            app(ProductSearchText::class)->refresh($variant->product_id);
        };

        self::saved(static function (self $variant) use ($refresh): void {
            if ($variant->wasRecentlyCreated || $variant->wasChanged(['sku', 'barcode', 'deleted_at'])) {
                $refresh($variant);
            }
        });
        self::deleted($refresh);
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
            'trashed_with_product' => 'boolean',
            'image_media_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
