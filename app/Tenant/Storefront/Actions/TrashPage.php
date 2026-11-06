<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Events\PageUnpublished;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Moves a page to the trash: shoppers stop seeing it, and it stops being the homepage. It can be restored.
 */
final readonly class TrashPage
{
    public function handle(Page $page): void
    {
        DB::transaction(static function () use ($page): void {
            // A soft delete saves only the time it was trashed, so the homepage flag is cleared first.
            $page->forceFill(['is_homepage' => false])->save();
            $page->delete();
        });

        if ($page->isPublished()) {
            PageUnpublished::dispatch($page->public_id);
        }

        StorefrontContentChanged::dispatch();
    }
}
