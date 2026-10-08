<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Catalog\CatalogSlug;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A brand the store sells, such as "Adidas", with its name and description in the store's languages.
 *
 * Moving a brand to the trash keeps it, so it can be restored and still shows
 * on products and old orders. Its slug is made once from the name in the
 * store's default language and only changes when staff change it, so links
 * keep working. Slugs are unique among brands outside the trash.
 *
 * @property int $id
 * @property string $public_id
 * @property array<string, string> $name By language, such as {"en": "Adidas"}; read one language with getTranslation().
 * @property array<string, string>|null $description By language.
 * @property string $slug Lower-case letters, numbers and single hyphens, such as "adidas".
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class Brand extends Model implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<BrandFactory> */
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
     * Made once from the name in the store's default language, then left alone; a name with no Latin letters gets "brand".
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(static function (self $brand): string {
                $name = (string) $brand->getTranslation('name', $brand->getFallbackLocale());

                return Str::slug($name) === '' ? 'brand' : $name;
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(CatalogSlug::MAX_LENGTH)
            ->preventOverwrite()
            ->doNotGenerateSlugsOnUpdate();
    }

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
