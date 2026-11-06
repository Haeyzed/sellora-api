<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Services;

use App\Tenant\Storefront\Models\Page;

/**
 * A published page's body as shoppers get it: cleaned again with today's PageHtml rules, so tightening them also cleans pages saved before, and cached so it is cleaned once per change.
 */
final readonly class ServedPageBody
{
    public function __construct(
        private PageHtml $pageHtml,
        private StorefrontContentCache $storefrontContentCache,
    ) {}

    /**
     * The body in the language given (or the store's default), cleaned; null when the page has none.
     */
    public function of(Page $page, string $locale): ?string
    {
        $saved = $page->getTranslation('body', $locale);

        if (! is_string($saved) || $saved === '') {
            return null;
        }

        $key = 'page.'.$page->id.'.'.$locale.'.rules-'.PageHtml::VERSION.'.'.$page->updated_at?->getTimestamp();

        return $this->storefrontContentCache->remember($key, fn (): string => $this->pageHtml->clean($saved));
    }
}
