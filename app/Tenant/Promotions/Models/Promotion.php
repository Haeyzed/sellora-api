<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Promotions\Enums\DiscountClass;
use App\Tenant\Promotions\Enums\PromotionAppliesTo;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Enums\PromotionStatus;
use App\Tenant\Promotions\Enums\PromotionType;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\PromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A discount applied automatically or with a code: a percentage, a fixed amount, free shipping, or buy X get Y.
 *
 * Amounts are in the store's base currency, with optional exact amounts per
 * offered currency (currencyAmounts); without one, the base amount is
 * converted at the rate the checkout uses. Starts and ends are stored in UTC
 * and shown in the store's timezone. Archiving (soft delete) keeps it for
 * the orders that used it; it never applies again. Audited.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name For staff.
 * @property array<string, string> $title What customers see, by language.
 * @property PromotionMethod $method
 * @property PromotionType $type
 * @property PromotionAppliesTo|null $applies_to Percentage and fixed amount only.
 * @property Decimal|null $percent Percentage and buy X get Y only: up to 100, two decimals.
 * @property string|null $currency The base currency the amounts are in; null when there are none.
 * @property Money|null $discount Fixed amount only.
 * @property Money|null $max_discount The most it takes off; not for a fixed amount.
 * @property Money|null $min_subtotal What the eligible lines must cost together, before discounts.
 * @property int|null $min_quantity How many eligible items the basket must have.
 * @property int|null $buy_quantity Buy X get Y only.
 * @property int|null $get_quantity Buy X get Y only.
 * @property int|null $max_applications Buy X get Y only: how many times it repeats in one order at most.
 * @property bool $includes_sale_items Whether items with a compare-at price are eligible; they aren't unless set.
 * @property bool $first_order_only
 * @property bool $combines_with_product_discounts
 * @property bool $combines_with_order_discounts
 * @property bool $combines_with_shipping_discounts
 * @property int|null $usage_limit How many orders may use it in all.
 * @property int|null $usage_limit_per_customer How many orders one customer (by account or email) may use it on.
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at Exclusive.
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at When it was archived.
 * @property-read Collection<int, PromotionCurrencyAmount> $currencyAmounts
 * @property-read Collection<int, PromotionCode> $codes
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, CustomerGroup> $customerGroups
 */
final class Promotion extends Model implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    use HasPublicId;
    use HasStoreTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    protected $fillable = [
        'name',
        'method',
        'type',
        'applies_to',
        'percent',
        'discount',
        'max_discount',
        'min_subtotal',
        'min_quantity',
        'buy_quantity',
        'get_quantity',
        'max_applications',
        'includes_sale_items',
        'first_order_only',
        'combines_with_product_discounts',
        'combines_with_order_discounts',
        'combines_with_shipping_discounts',
        'usage_limit',
        'usage_limit_per_customer',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'includes_sale_items' => false,
        'first_order_only' => false,
        'combines_with_product_discounts' => false,
        'combines_with_order_discounts' => false,
        'combines_with_shipping_discounts' => false,
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * The class its discount is: shipping for free shipping, product for buy X get Y, otherwise what it applies to.
     */
    public function discountClass(): DiscountClass
    {
        return match ($this->type) {
            PromotionType::FreeShipping => DiscountClass::Shipping,
            PromotionType::BuyXGetY => DiscountClass::Product,
            default => $this->applies_to === PromotionAppliesTo::Order ? DiscountClass::Order : DiscountClass::Product,
        };
    }

    /**
     * Whether it applies together with a discount of the class.
     */
    public function combinesWith(DiscountClass $class): bool
    {
        return match ($class) {
            DiscountClass::Product => $this->combines_with_product_discounts,
            DiscountClass::Order => $this->combines_with_order_discounts,
            DiscountClass::Shipping => $this->combines_with_shipping_discounts,
        };
    }

    /**
     * @return list<DiscountClass>
     */
    public function combinableClasses(): array
    {
        return array_values(array_filter(DiscountClass::cases(), $this->combinesWith(...)));
    }

    public function status(?CarbonImmutable $at = null): PromotionStatus
    {
        $at ??= CarbonImmutable::now();

        return match (true) {
            $this->trashed() => PromotionStatus::Archived,
            ! $this->is_active => PromotionStatus::Inactive,
            $this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($at) => PromotionStatus::Ended,
            $this->starts_at->greaterThan($at) => PromotionStatus::Scheduled,
            default => PromotionStatus::Running,
        };
    }

    /**
     * Promotions with the status now.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithStatus(Builder $query, PromotionStatus $status, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        match ($status) {
            PromotionStatus::Archived => $query->onlyTrashed(),
            PromotionStatus::Inactive => $query->where('is_active', false),
            PromotionStatus::Ended => $query->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<=', $at),
            PromotionStatus::Scheduled => $query->where('is_active', true)->where('starts_at', '>', $at)->where(static fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>', $at)),
            PromotionStatus::Running => $query->where('is_active', true)->where('starts_at', '<=', $at)->where(static fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>', $at)),
        };
    }

    /**
     * The codes customers enter to get it; only a code promotion has any.
     *
     * @return HasMany<PromotionCode, $this>
     */
    public function codes(): HasMany
    {
        return $this->hasMany(PromotionCode::class);
    }

    /**
     * @return HasMany<PromotionCurrencyAmount, $this>
     */
    public function currencyAmounts(): HasMany
    {
        return $this->hasMany(PromotionCurrencyAmount::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_products')->withPivot('list')->withTrashed();
    }

    /**
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'promotion_variants')->withPivot('list')->withTrashed();
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promotion_categories')->withPivot('list')->withTrashed();
    }

    /**
     * @return BelongsToMany<CustomerGroup, $this>
     */
    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'promotion_customer_groups');
    }

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PromotionMethod::class,
            'type' => PromotionType::class,
            'applies_to' => PromotionAppliesTo::class,
            'percent' => DecimalCast::class.':2',
            'discount' => MoneyCast::class.':currency',
            'max_discount' => MoneyCast::class.':currency',
            'min_subtotal' => MoneyCast::class.':currency',
            'includes_sale_items' => 'boolean',
            'first_order_only' => 'boolean',
            'combines_with_product_discounts' => 'boolean',
            'combines_with_order_discounts' => 'boolean',
            'combines_with_shipping_discounts' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
