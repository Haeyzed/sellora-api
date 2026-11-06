<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Tenant\Storefront\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published page as shoppers see it, in the language they asked for (or the store's default), with its body cleaned for showing.
 *
 * @property Page $resource
 */
final class PublishedPageResource extends JsonResource
{
    public function __construct(Page $page, private readonly ?string $body)
    {
        parent::__construct($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;
        $locale = app()->getLocale();
        $seoTitle = $page->getTranslation('seo_title', $locale);
        $seoDescription = $page->getTranslation('seo_description', $locale);

        return [
            'id' => $page->public_id,
            'slug' => $page->slug,
            'title' => $page->getTranslation('title', $locale),
            /** The page's content as cleaned HTML, safe to show as it is; null when it has none. */
            'body' => $this->body,
            /** What search engines show; the title or the store's default description when they are null. */
            'seo' => [
                'title' => $seoTitle === '' ? null : $seoTitle,
                'description' => $seoDescription === '' ? null : $seoDescription,
            ],
            'is_homepage' => $page->is_homepage,
            'published_at' => $page->published_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }
}
