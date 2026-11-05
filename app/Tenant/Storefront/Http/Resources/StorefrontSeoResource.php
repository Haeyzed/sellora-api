<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How the storefront presents itself to search engines and social sites, as the storefront needs it, in the language asked for (or the store's default).
 *
 * @property StorefrontSettings $resource
 */
final class StorefrontSeoResource extends JsonResource
{
    public function __construct(StorefrontSettings $settings, private readonly string $storeName)
    {
        parent::__construct($settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;
        $description = $settings->getTranslation('seo_description', app()->getLocale());
        $shareImage = $settings->getFirstMedia(StorefrontSettings::SHARE_IMAGE);

        return [
            /** The store's name, for {store} in the title pattern. */
            'store_name' => $this->storeName,
            /** How to title a page: put the page's title in place of {title} and the store's name in place of {store}. */
            'title_pattern' => $settings->seo_title_pattern,
            /** What search engines show for a page without a description of its own; null when there is none. */
            'description' => $description === '' ? null : $description,
            /** For the robots meta tag, such as "index, follow". */
            'robots' => $settings->robots->metaContent(),
            /** The image social sites show when a page is shared; null when there is none. */
            'share_image' => $shareImage === null ? null : new StorefrontImageResource($shareImage),
        ];
    }
}
