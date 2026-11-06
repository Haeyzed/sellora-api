<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\StorefrontImageUploader;
use App\Tenant\Storefront\Enums\BannerKind;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\BannerHasNoImageException;
use App\Tenant\Storefront\Models\Banner;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sets an image banner's image, replacing the one it had. Announcements have no image.
 */
final readonly class ChangeBannerImage
{
    public function __construct(private StorefrontImageUploader $storefrontImageUploader) {}

    /**
     * @throws BannerHasNoImageException When the banner is an announcement.
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     */
    public function handle(Banner $banner, UploadedFile $image): Media
    {
        if ($banner->kind !== BannerKind::Image) {
            throw new BannerHasNoImageException;
        }

        $media = $this->storefrontImageUploader->add($banner, $image, Banner::IMAGE);
        StorefrontContentChanged::dispatch();

        return $media;
    }
}
