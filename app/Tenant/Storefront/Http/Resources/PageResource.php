<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A page as staff see it, drafts included, with its texts in every language they were written in.
 *
 * @property Page $resource
 */
final class PageResource extends JsonResource
{
    public function __construct(Page $page)
    {
        parent::__construct($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;

        return [
            'id' => $page->public_id,
            /**
             * By language, such as {"en": "About us"}.
             *
             * @var array<string, string>
             */
            'title' => $page->getTranslations('title'),
            /** Used in the page's storefront address, such as "about-us". */
            'slug' => $page->slug,
            /**
             * By language: the cleaned HTML; null when the page has no body.
             *
             * @var array<string, string>|null
             */
            'body' => self::translationsOrNull($page, 'body'),
            /**
             * By language; null when there is none.
             *
             * @var array<string, string>|null
             */
            'seo_title' => self::translationsOrNull($page, 'seo_title'),
            /**
             * By language; null when there is none.
             *
             * @var array<string, string>|null
             */
            'seo_description' => self::translationsOrNull($page, 'seo_description'),
            /** "draft" or "published". */
            'status' => $page->status->value,
            /** Whether the storefront opens on this page (once it is published). */
            'is_homepage' => $page->is_homepage,
            /** The images uploaded for its body; put their addresses in the body to show them. */
            'images' => $this->whenLoaded('media', static fn (): array => array_values($page->getMedia(Page::IMAGES)->map(static fn (Media $image): StorefrontImageResource => new StorefrontImageResource($image))->all())),
            /** When it was first published; null while it never was. */
            'published_at' => $page->published_at?->toIso8601String(),
            /** When it was moved to the trash; null when it isn't in the trash. */
            'trashed_at' => $page->deleted_at?->toIso8601String(),
            'created_at' => $page->created_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private static function translationsOrNull(Page $page, string $field): ?array
    {
        $translations = $page->getTranslations($field);

        return $translations === [] ? null : $translations;
    }
}
