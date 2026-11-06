<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Tenant\Catalog\Enums\ProductLinkType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product a merchant picked to show beside another, as a related product, an upsell or a cross-sell.
 *
 * @property int $id
 * @property int $product_id
 * @property ProductLinkType $type
 * @property int $linked_product_id
 * @property int $position From 0, among the product's links of this kind.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 * @property-read Product $linkedProduct
 */
final class ProductLink extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['product_id', 'type', 'linked_product_id', 'position'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The linked product, even from the trash, so staff see a link to a product that went there.
     *
     * @return BelongsTo<Product, $this>
     */
    public function linkedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'linked_product_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductLinkType::class,
            'position' => 'integer',
        ];
    }
}
