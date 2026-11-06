<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Storefront\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A banner or announcement as shoppers see it, in their language. Only those shown now reach this resource.
 *
 * @property Banner $resource
 */
final class PublishedBannerResource extends JsonResource
{
    public function __construct(Banner $banner)
    {
        parent::__construct($banner);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $banner = $this->resource;
        $locale = app()->getLocale();
        $image = $banner->relationLoaded('media') ? $banner->getFirstMedia(Banner::IMAGE) : null;

        return [
            'id' => $banner->public_id,
            /** "image" or "announcement". */
            'kind' => $banner->kind->value,
            /** In the language asked for, or the store's default: the headline (also the image's description), or the announcement's message. */
            'title' => $banner->getTranslation('title', $locale),
            /** Null when there is none. */
            'body' => self::textOrNull($banner->getTranslation('body', $locale)),
            /** Where it leads; null for no link. */
            'link_url' => $banner->link_url,
            /** The button's text; null when there is none. */
            'link_label' => self::textOrNull($banner->getTranslation('link_label', $locale)),
            /** Such as "#ffffff"; null for the theme's. */
            'text_color' => $banner->text_color,
            /** Such as "#1a1a1a"; null for the theme's. */
            'background_color' => $banner->background_color,
            /** An image banner's image; null for an announcement. */
            'image' => $image === null ? null : new StorefrontImageResource($image),
            /** When it stops being shown, for a countdown; null for never. */
            'ends_at' => $banner->ends_at?->toIso8601String(),
        ];
    }

    /**
     * A translation as shown, or null when it is empty.
     *
     * @param  mixed  $text  What the translations package returns, which it types as mixed.
     */
    private static function textOrNull(mixed $text): ?string
    {
        return is_string($text) && $text !== '' ? $text : null;
    }
}
