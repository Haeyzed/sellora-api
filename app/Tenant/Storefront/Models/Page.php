<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Media\Concerns\HasStorefrontImages;
use App\Shared\Media\StorefrontImage;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use App\Tenant\Storefront\Enums\PageStatus;
use App\Tenant\Storefront\PageSlug;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * One of the store's own storefront pages, such as "About us", in the store's languages. Shoppers see it once it is published.
 *
 * Its body is HTML cleaned by PageHtml when saved, and again when served.
 * Its slug is made once from the title in the store's default language and
 * only changes when staff change it, so links keep working. Moving a page to
 * the trash keeps it, so it can be restored.
 *
 * @property int $id
 * @property string $public_id
 * @property array<string, string> $title By language; read one language with getTranslation().
 * @property string $slug
 * @property array<string, string>|null $body By language: cleaned HTML.
 * @property array<string, string>|null $seo_title By language.
 * @property array<string, string>|null $seo_description By language.
 * @property PageStatus $status
 * @property CarbonImmutable|null $published_at When it was first published.
 * @property bool $is_homepage Whether the storefront opens on it.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class Page extends Model implements HasMedia
{
    /** Images embedded in its body, on the public disk. */
    public const string IMAGES = 'images';

    use HasPublicId;
    use HasSlug;
    use HasStorefrontImages;
    use HasStoreTranslations;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body', 'seo_title', 'seo_description'];

    protected $fillable = [];

    protected $attributes = [
        'status' => 'draft',
        'is_homepage' => false,
    ];

    /**
     * Made once from the title in the store's default language, then left alone; a title with no Latin letters gets "page".
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(static function (self $page): string {
                $title = $page->getTranslation('title', $page->getFallbackLocale());

                return is_string($title) && Str::slug($title) !== '' ? $title : 'page';
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(PageSlug::MAX_LENGTH)
            ->preventOverwrite()
            ->doNotGenerateSlugsOnUpdate();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGES)->useDisk(StorefrontImage::disk());
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }

    /**
     * Only the pages shoppers may see: published, outside the trash.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PageStatus::Published->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'is_homepage' => 'boolean',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
