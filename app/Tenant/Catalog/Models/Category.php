<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Catalog\CatalogSlug;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A category in the store's tree, such as "Clothing > Men > Shirts", with its name and description in the store's languages.
 *
 * Categories nest up to a configured depth and are ordered among their
 * siblings by position. Moving one to the trash keeps it, so it can be
 * restored and still shows on old orders. Its slug is made once from the
 * name in the default language and only changes when staff change it.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $parent_id
 * @property array<string, string> $name By language; read one language with getTranslation().
 * @property array<string, string>|null $description By language.
 * @property string $slug Lower-case letters, numbers and single hyphens, such as "mens-shirts".
 * @property int $position Its place among its siblings, from 0.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Category|null $parent
 * @property-read int|null $children_count
 */
final class Category extends Model implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasPublicId;
    use HasSlug;
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
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * Made once from the name in the store's default language, then left alone; a name with no Latin letters gets "category".
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(static function (self $category): string {
                $name = (string) $category->getTranslation('name', $category->getFallbackLocale());

                return Str::slug($name) === '' ? 'category' : $name;
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(CatalogSlug::MAX_LENGTH)
            ->preventOverwrite()
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * The category it sits under, even when that one is in the trash; null at the top level.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /**
     * Its subcategories outside the trash.
     *
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
