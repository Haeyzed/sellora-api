<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Media\Concerns\HasStorefrontImages;
use App\Shared\Media\StorefrontImage;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use App\Tenant\Storefront\Enums\BannerKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * One of the storefront's banners: an image placed somewhere on the storefront, or an announcement in the bar along the top.
 *
 * Shoppers see it while it is switched on and within its times, which are
 * kept in UTC. An image banner is shown only once it has its image.
 *
 * @property int $id
 * @property string $public_id
 * @property BannerKind $kind
 * @property string|null $position One of config storefront.banner_positions; null for an announcement.
 * @property array<string, string> $title By language; read one language with getTranslation().
 * @property array<string, string>|null $body By language.
 * @property string|null $link_url
 * @property array<string, string>|null $link_label By language.
 * @property string|null $text_color Such as "#ffffff".
 * @property string|null $background_color Such as "#1a1a1a".
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at Not included.
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class Banner extends Model implements HasMedia
{
    /** An image banner's image, on the public disk. */
    public const string IMAGE = 'image';

    /** A colour as "#" and six hexadecimal digits, such as "#1a1a1a". */
    public const string COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    use HasPublicId;
    use HasStorefrontImages;
    use HasStoreTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body', 'link_label'];

    protected $fillable = [];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE)->singleFile()->useDisk(StorefrontImage::disk());
    }

    /**
     * Only the banners shoppers see at the given moment: switched on, started, and not yet ended.
     *
     * @param  Builder<self>  $query
     */
    public function scopeShownAt(Builder $query, CarbonImmutable $moment): void
    {
        $query->where('is_active', true)
            ->where(static fn (Builder $starts) => $starts->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
            ->where(static fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>', $moment));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BannerKind::class,
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
