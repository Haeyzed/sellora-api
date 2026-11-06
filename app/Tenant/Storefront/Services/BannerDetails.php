<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Services;

use App\Tenant\Storefront\Data\BannerData;
use App\Tenant\Storefront\Models\Banner;

/**
 * Applies staff's details to a banner, without saving it, for adding and changing banners alike. Removing the link removes its button text too.
 */
final readonly class BannerDetails
{
    public function apply(Banner $banner, BannerData $changes): void
    {
        foreach ($changes->attributes as $column => $value) {
            $banner->setAttribute($column, $value);
        }

        foreach ($changes->translations as $column => $translations) {
            $banner->changeTranslations($column, $translations);
        }

        $removes = $banner->link_url === null ? [...$changes->removes, 'link_label'] : $changes->removes;

        foreach (array_unique($removes) as $column) {
            $banner->setAttribute($column, null);
        }
    }
}
