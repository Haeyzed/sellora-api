<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Storefront\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A banner as staff see it, switched off and scheduled ones included, with its texts in every language they were written in.
 *
 * @property Banner $resource
 */
final class BannerResource extends JsonResource
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

        return [
            'id' => $banner->public_id,
            /** "image" or "announcement". */
            'kind' => $banner->kind->value,
            /** Where an image banner is placed; null for an announcement. */
            'position' => $banner->position,
            /**
             * By language: the headline, or the announcement's message.
             *
             * @var array<string, string>
             */
            'title' => $banner->getTranslations('title'),
            /**
             * By language; null when there is none.
             *
             * @var array<string, string>|null
             */
            'body' => self::translationsOrNull($banner, 'body'),
            /** Where it leads; null for no link. */
            'link_url' => $banner->link_url,
            /**
             * By language: the button's text; null when there is none.
             *
             * @var array<string, string>|null
             */
            'link_label' => self::translationsOrNull($banner, 'link_label'),
            /** Such as "#ffffff"; null for the theme's. */
            'text_color' => $banner->text_color,
            /** Such as "#1a1a1a"; null for the theme's. */
            'background_color' => $banner->background_color,
            /** When it starts being shown; null for at once. */
            'starts_at' => $banner->starts_at?->toIso8601String(),
            /** When it stops being shown (not included); null for never. */
            'ends_at' => $banner->ends_at?->toIso8601String(),
            'is_active' => $banner->is_active,
            'sort_order' => $banner->sort_order,
            /** An image banner's image; null until it is uploaded (shoppers don't see the banner until then) and for an announcement. */
            'image' => $this->whenLoaded('media', static function () use ($banner): ?StorefrontImageResource {
                $image = $banner->getFirstMedia(Banner::IMAGE);

                return $image === null ? null : new StorefrontImageResource($image);
            }),
            'created_at' => $banner->created_at?->toIso8601String(),
            'updated_at' => $banner->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private static function translationsOrNull(Banner $banner, string $field): ?array
    {
        $translations = $banner->getTranslations($field);

        return $translations === [] ? null : $translations;
    }
}
