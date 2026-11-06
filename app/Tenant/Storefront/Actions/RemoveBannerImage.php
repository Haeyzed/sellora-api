<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Banner;

/**
 * Deletes an image banner's image, with its file. Shoppers stop seeing the banner until it has an image again.
 */
final readonly class RemoveBannerImage
{
    public function handle(Banner $banner): void
    {
        $banner->clearMediaCollection(Banner::IMAGE);
        StorefrontContentChanged::dispatch();
    }
}
