<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Data\BannerData;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Banner;
use App\Tenant\Storefront\Services\BannerDetails;

/**
 * Changes a banner's texts, link, colours, times, order or whether it is switched on. Its kind never changes.
 *
 * Removing the link removes its button text too.
 */
final readonly class UpdateBanner
{
    public function __construct(private BannerDetails $bannerDetails) {}

    public function handle(Banner $banner, BannerData $changes): Banner
    {
        $this->bannerDetails->apply($banner, $changes);
        $banner->save();

        StorefrontContentChanged::dispatch();

        return $banner;
    }
}
