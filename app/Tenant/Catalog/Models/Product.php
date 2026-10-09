<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Media\Concerns\HasStorefrontImages;
use App\Shared\Media\StorefrontImage;
use App\Tenant\Catalog\CatalogSlug;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\ProductFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A product the store sells, such as "Linen shirt", with its name and description in the store's languages.
 *
 * What is actually bought is one of its variants, which carry the prices,
 * SKUs and shipping details; every product has at least one. A product
 * starts as a draft. Its slug is made once from the name in the default
 * language and only changes when staff change it.
 *
 * @property int $id
 * @property string $public_id
 * @property ProductStatus $status
 * @property array<string, string> $name By language; read one language with getTranslation().
 * @property array<string, string>|null $description By language.
 * @property string $slug Lower-case letters, numbers and single hyphens, such as "linen-shirt".
 * @property int|null $brand_id
 * @property int|null $primary_category_id Always one of its categories.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Brand|null $brand
 * @property-read Category|null $primaryCategory
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, Attribute> $options
 */
final class Product extends Model implements AuditableContract, HasMedia
{
    /** Its images, in order; the first is shown in lists. */
    public const string GALLERY = 'gallery';

    use Auditable;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasPublicId;
    use HasSlug;
    use HasStorefrontImages;
    use HasStoreTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'name',
        'description',
        'slug',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * Made once from the name in the store's default language, then left alone; a name with no Latin letters gets "product".
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(static function (self $product): string {
                $name = (string) $product->getTranslation('name', $product->getFallbackLocale());

                return Str::slug($name) === '' ? 'product' : $name;
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(CatalogSlug::MAX_LENGTH)
            ->preventOverwrite()
            ->doNotGenerateSlugsOnUpdate();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::GALLERY)->useDisk(StorefrontImage::DISK);
    }

    /**
     * Its brand, even when the brand is in the trash.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /**
     * The category it is mainly listed under, even when that category is in the trash.
     *
     * @return BelongsTo<Category, $this>
     */
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id')->withTrashed();
    }

    /**
     * Every category it is in, including categories in the trash.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTrashed();
    }

    /**
     * The attributes its variants differ by, such as Size and Colour, in the order customers see them. Empty for a product with a single variant.
     *
     * @return BelongsToMany<Attribute, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)->withPivot('position')->orderByPivot('position');
    }

    /**
     * Its variants outside the trash, in their order.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'brand_id' => 'integer',
            'primary_category_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
