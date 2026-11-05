<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The storefront settings as staff see them, with texts in every language they were written in.
 *
 * @property StorefrontSettings $resource
 */
final class StorefrontSettingsResource extends JsonResource
{
    public function __construct(StorefrontSettings $settings)
    {
        parent::__construct($settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;
        $description = $settings->getTranslations('seo_description');
        $shareImage = $settings->getFirstMedia(StorefrontSettings::SHARE_IMAGE);

        return [
            /** How a page is titled for search engines, such as "{title} · {store}". */
            'seo_title_pattern' => $settings->seo_title_pattern,
            /**
             * By language: what search engines show for pages without a description of their own; null when there is none.
             *
             * @var array<string, string>|null
             */
            'seo_description' => $description === [] ? null : $description,
            /** "index_follow", "noindex_follow" or "noindex_nofollow". */
            'robots' => $settings->robots->value,
            /** The image social sites show when a page is shared; null when there is none. */
            'share_image' => $shareImage === null ? null : new StorefrontImageResource($shareImage),
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
