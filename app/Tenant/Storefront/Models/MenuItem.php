<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Models;

use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use App\Tenant\Storefront\Enums\MenuLinkType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One link in a storefront menu, going to exactly one page, category, brand, product or address, with links of its own under it at the top level.
 *
 * A menu's links are always replaced together, so a link has no public ID
 * of its own.
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property array<string, string> $label By language; read one language with getTranslation().
 * @property int|null $page_id
 * @property int|null $category_id
 * @property int|null $brand_id
 * @property int|null $product_id
 * @property string|null $url
 * @property bool $opens_in_new_tab
 * @property int $position Its place among its siblings, from 0.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, MenuItem> $children
 * @property-read Page|null $page
 * @property-read Category|null $category
 * @property-read Brand|null $brand
 * @property-read Product|null $product
 * @property-read Page|null $shownPage
 * @property-read Category|null $shownCategory
 * @property-read Brand|null $shownBrand
 * @property-read Product|null $shownProduct
 */
final class MenuItem extends Model
{
    /** An address staff may link to: https, mailto:, tel: or a path on the storefront (never "//", which leaves it), with no spaces. The same rule as page links. */
    public const string URL_PATTERN = '#^(https://[^\s/]|mailto:\S|tel:\S|/(?!/))\S*$#';

    use HasStoreTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['label'];

    protected $fillable = [];

    protected $attributes = [
        'opens_in_new_tab' => false,
    ];

    /**
     * Where the link goes, read from the one destination column that is filled in.
     */
    public function linkType(): MenuLinkType
    {
        foreach (MenuLinkType::cases() as $linkType) {
            if ($this->getAttribute($linkType->column()) !== null) {
                return $linkType;
            }
        }

        throw new LogicException('A menu link always has exactly one destination.');
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * The links under this one, in order.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * The page it goes to, even in the trash, for staff; shoppers' links use only what they may see.
     *
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    /**
     * The category it goes to, even in the trash, for staff; shoppers' links use only what they may see.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * The brand it goes to, even in the trash, for staff; shoppers' links use only what they may see.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /**
     * The product it goes to, even in the trash, for staff; shoppers' links use only what they may see.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * The page it goes to, only while shoppers may see it (published, outside the trash).
     *
     * @return BelongsTo<Page, $this>
     */
    public function shownPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id')->published();
    }

    /**
     * The category it goes to, only while it is outside the trash.
     *
     * @return BelongsTo<Category, $this>
     */
    public function shownCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * The brand it goes to, only while it is outside the trash.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function shownBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * The product it goes to, only while shoppers may see it (section 3.3).
     *
     * @return BelongsTo<Product, $this>
     */
    public function shownProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->visibleToCustomers();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_in_new_tab' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
