<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use App\Tenant\Storefront\Data\PageData;
use App\Tenant\Storefront\Models\Page;
use App\Tenant\Storefront\PageSlug;
use App\Tenant\Storefront\Services\PageHtml;
use Illuminate\Validation\Rule;

/**
 * The rules for a page's texts and slug, for adding and changing pages.
 */
trait ValidatesPageTexts
{
    use ValidatesStoreTranslations;

    /** The translated fields a page has, by request key and column. */
    private const array TRANSLATED = ['title' => 'title', 'body' => 'body', 'seo_title' => 'seo_title', 'seo_description' => 'seo_description'];

    /**
     * @return array<string, mixed>
     */
    protected function pageTextRules(bool $creating): array
    {
        $page = $this->route('page');

        return [
            /**
             * By language, such as {"en": "About us"}; up to 200 characters each. Must include the store's default language when the page is added.
             *
             * @var array<string, string|null>
             */
            'title' => [$creating ? 'required' : 'sometimes', 'array', $this->storeTranslatedText(200, requiresDefault: $creating)],
            /**
             * By language: the page's content as HTML, up to 100,000 characters each. Only text formatting, headings, lists, quotes, tables, links (https, relative paths, mailto: and tel:) and the store's own images (uploaded to the page) are kept; everything else is removed before it is saved.
             *
             * @var array<string, string|null>|null
             */
            'body' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(PageHtml::MAX_LENGTH)],
            /**
             * By language: the title search engines show, when it differs from the page's title; up to 200 characters each.
             *
             * @var array<string, string|null>|null
             */
            'seo_title' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(200)],
            /**
             * By language: the description search engines show; up to 320 characters each.
             *
             * @var array<string, string|null>|null
             */
            'seo_description' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(320)],
            /** Lower-case letters, numbers and single hyphens, such as "about-us". Made from the title in the default language when the page is added without one; changing the title doesn't change it. */
            'slug' => [
                'sometimes', 'string', 'max:'.PageSlug::MAX_LENGTH, 'regex:'.PageSlug::PATTERN,
                Rule::unique(Page::class, 'slug')->whereNull('deleted_at')->ignore($page instanceof Page ? $page->getKey() : null),
            ],
        ];
    }

    public function pageData(): PageData
    {
        $slug = $this->validated('slug');
        $removes = [];

        foreach (self::TRANSLATED as $key => $column) {
            if ($key !== 'title' && $this->has($key) && $this->validated($key) === null) {
                $removes[] = $column;
            }
        }

        return new PageData(
            title: $this->validatedTranslations('title'),
            body: $this->validatedTranslations('body'),
            seoTitle: $this->validatedTranslations('seo_title'),
            seoDescription: $this->validatedTranslations('seo_description'),
            slug: is_string($slug) ? $slug : null,
            removes: $removes,
        );
    }
}
