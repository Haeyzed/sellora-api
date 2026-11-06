<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Services;

use App\Tenant\Storefront\Data\PageData;
use App\Tenant\Storefront\Models\Page;

/**
 * Applies staff's texts to a page, cleaning each language's body with PageHtml before it is kept, for adding and changing pages alike.
 */
final readonly class PageTexts
{
    public function __construct(private PageHtml $pageHtml) {}

    public function apply(Page $page, PageData $pageData): void
    {
        foreach ($pageData->removes as $field) {
            // In every language: setting a translated field to null would clear only the current language's text.
            $page->forgetTranslations($field, asNull: true);
        }

        if ($pageData->title !== null) {
            $page->changeTranslations('title', $pageData->title);
        }

        if ($pageData->body !== null) {
            $page->changeTranslations('body', array_map(fn (?string $html): ?string => $html === null ? null : $this->pageHtml->clean($html), $pageData->body));
        }

        if ($pageData->seoTitle !== null) {
            $page->changeTranslations('seo_title', $pageData->seoTitle);
        }

        if ($pageData->seoDescription !== null) {
            $page->changeTranslations('seo_description', $pageData->seoDescription);
        }

        if ($pageData->slug !== null) {
            $page->slug = $pageData->slug;
        }
    }
}
