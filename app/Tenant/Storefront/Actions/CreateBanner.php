<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Data\BannerData;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Banner;
use App\Tenant\Storefront\Services\BannerDetails;
use LogicException;

/**
 * Adds an image banner or an announcement. An image banner is shown once its image is uploaded.
 */
final readonly class CreateBanner
{
    public function __construct(private BannerDetails $bannerDetails) {}

    public function handle(BannerData $bannerData): Banner
    {
        $banner = new Banner;
        $banner->kind = $bannerData->kind ?? throw new LogicException('A new banner needs its kind.');

        $this->bannerDetails->apply($banner, $bannerData);
        $banner->save();

        StorefrontContentChanged::dispatch();

        return $banner;
    }
}
