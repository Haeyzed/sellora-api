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
 * Changes a page's texts or slug. Languages that aren't sent keep their text; a body is cleaned of anything unsafe before it is kept.
 *
 * Changing the title doesn't change the slug, so links keep working; staff
 * change the slug on purpose. A published page shows the change on the
 * storefront at once.
 */
final readonly class UpdatePage
{
    public function __construct(private PageTexts $pageTexts) {}

    /**
     * @throws PageSlugTakenException When the new slug is used by another page outside the trash.
     */
    public function handle(Page $page, PageData $changes): Page
    {
        $this->pageTexts->apply($page, $changes);

        try {
            $page->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new PageSlugTakenException(previous: $exception);
        }

        StorefrontContentChanged::dispatch();

        return $page;
    }
}
