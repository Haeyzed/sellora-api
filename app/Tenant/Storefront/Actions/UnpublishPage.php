<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Enums\PageStatus;
use App\Tenant\Storefront\Events\PageUnpublished;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Page;

/**
 * Takes a page off the storefront, back to a draft. Menus and policy links to it stop showing until it is published again.
 */
final readonly class UnpublishPage
{
    public function handle(Page $page): Page
    {
        if (! $page->isPublished()) {
            return $page;
        }

        $page->forceFill(['status' => PageStatus::Draft])->save();
        PageUnpublished::dispatch($page->public_id);
        StorefrontContentChanged::dispatch();

        return $page;
    }
}
