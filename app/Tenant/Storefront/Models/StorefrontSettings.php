<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Models;

use App\Shared\Media\Concerns\HasStorefrontImages;
use App\Shared\Media\StorefrontImage;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use App\Tenant\Storefront\Enums\PolicyPage;
use App\Tenant\Storefront\Enums\RobotsDirective;
use App\Tenant\Storefront\Exceptions\MissingStorefrontSettingsException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\MediaLibrary\HasMedia;

/**
 * How the storefront presents itself to search engines and social sites. One row, created with the store from the defaults in config/storefront.php (StorefrontSettingsDefaults), never on read.
 *
 * @property int $id Always 1.
 * @property string $seo_title_pattern How a page is titled for search engines, such as "{title} · {store}".
 * @property array<string, string>|null $seo_description By language: the description search engines show for pages without their own.
 * @property RobotsDirective $robots Whether search engines may list the storefront and follow its links.
 * @property int|null $privacy_page_id
 * @property int|null $terms_page_id
 * @property int|null $refunds_page_id
 * @property int|null $shipping_page_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class StorefrontSettings extends Model implements AuditableContract, HasMedia
{
    /** The image social sites show when a storefront page is shared; a new one replaces the old. */
    public const string SHARE_IMAGE = 'share_image';

    use Auditable;
    use HasStorefrontImages;
    use HasStoreTranslations;

    public $incrementing = false;

    /**
     * @var list<string>
     */
    public array $translatable = ['seo_description'];

    protected $table = 'storefront_settings';

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'updated_at'];

    /**
     * The store's storefront settings.
     *
     * @throws MissingStorefrontSettingsException When the store has none: a bug, never filled in with defaults.
     */
    public static function current(): self
    {
        return self::query()->find(1) ?? throw new MissingStorefrontSettingsException;
    }

    /**
     * The pages serving each policy, by PolicyPage value; null where none is chosen. Pages in the trash included: the storefront shows only published ones.
     *
     * @return array<string, Page|null>
     */
    public function policyPages(): array
    {
        $ids = array_filter(array_map(fn (PolicyPage $policy): mixed => $this->getAttribute($policy->column()), PolicyPage::cases()), is_int(...));
        $pages = Page::withTrashed()->whereKey($ids)->get()->keyBy('id');
        $policyPages = [];

        foreach (PolicyPage::cases() as $policy) {
            $id = $this->getAttribute($policy->column());
            $policyPages[$policy->value] = is_int($id) ? $pages->get($id) : null;
        }

        return $policyPages;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::SHARE_IMAGE)->singleFile()->useDisk(StorefrontImage::disk());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'robots' => RobotsDirective::class,
            'privacy_page_id' => 'integer',
            'terms_page_id' => 'integer',
            'refunds_page_id' => 'integer',
            'shipping_page_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
