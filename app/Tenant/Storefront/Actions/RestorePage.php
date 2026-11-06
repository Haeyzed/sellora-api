<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\PageSlugTakenException;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Brings a page back from the trash, as it was (published or a draft), but not as the homepage.
 */
final readonly class RestorePage
{
    /**
     * @throws PageSlugTakenException When another page took its slug while it was in the trash.
     */
    public function handle(Page $page): Page
    {
        try {
            $page->restore();
        } catch (UniqueConstraintViolationException $exception) {
            throw new PageSlugTakenException(previous: $exception);
        }

        StorefrontContentChanged::dispatch();

        return $page;
    }
}
