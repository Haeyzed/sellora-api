<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Enums\PageStatus;
use App\Tenant\Storefront\Events\PagePublished;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Page;

/**
 * Puts a page on the storefront. Publishing a published page changes nothing; the first publication's time is kept.
 */
final readonly class PublishPage
{
    public function handle(Page $page): Page
    {
        if ($page->isPublished()) {
            return $page;
        }

        $page->forceFill(['status' => PageStatus::Published, 'published_at' => $page->published_at ?? now()])->save();
        PagePublished::dispatch($page->public_id);
        StorefrontContentChanged::dispatch();

        return $page;
    }
}
