<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a signed-in customer saved to buy later: a product, and optionally the variant they chose. It holds no quantity, price or stock.
 *
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $product_variant_id Null when saved before choosing a variant.
 * @property CarbonImmutable $created_at When it was saved.
 * @property-read Customer $customer
 * @property-read Product $product
 * @property-read ProductVariant|null $variant
 */
final class WishlistItem extends Model
{
    use HasPublicId;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['customer_id', 'product_id', 'product_variant_id'];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The product, even from the trash: the customer still sees what they saved, as unavailable.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * The variant chosen, even from the trash, shown as unavailable there.
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
            'created_at' => 'immutable_datetime',
        ];
    }
}
