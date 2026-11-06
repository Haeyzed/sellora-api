<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Data\PageData;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\PageSlugTakenException;
use App\Tenant\Storefront\Models\Page;
use App\Tenant\Storefront\Services\PageTexts;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a storefront page as a draft, such as "About us", with its texts in the store's languages and its body cleaned of anything unsafe.
 *
 * Without a chosen slug, one is made from the title in the default language.
 */
final readonly class CreatePage
{
    public function __construct(private PageTexts $pageTexts) {}

    /**
     * @throws PageSlugTakenException When the chosen slug is used by another page outside the trash.
     */
    public function handle(PageData $pageData): Page
    {
        $page = new Page;
        $this->pageTexts->apply($page, $pageData);

        try {
            $page->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new PageSlugTakenException(previous: $exception);
        }

        StorefrontContentChanged::dispatch();

        return $page;
    }
}
