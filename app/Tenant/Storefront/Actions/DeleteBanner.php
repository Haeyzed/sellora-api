<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Banner;

/**
 * Deletes a banner, with its image's file.
 */
final readonly class DeleteBanner
{
    public function handle(Banner $banner): void
    {
        $banner->delete();

        StorefrontContentChanged::dispatch();
    }
}
