<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\ChangeBannerImage;
use App\Tenant\Storefront\Actions\RemoveBannerImage;
use App\Tenant\Storefront\Exceptions\BannerHasNoImageException;
use App\Tenant\Storefront\Http\Requests\ManageBannerRequest;
use App\Tenant\Storefront\Http\Requests\UploadStorefrontImageRequest;
use App\Tenant\Storefront\Http\Resources\BannerResource;
use App\Tenant\Storefront\Models\Banner;
use Illuminate\Http\Response;

/**
 * An image banner's image.
 */
final class BannerImageController extends Controller
{
    /**
     * Set a banner's image.
     *
     * Needs the storefront.manage permission. Replaces the current one. JPEG,
     * PNG or WebP, never SVG, up to 10 MB and 6000 × 6000 pixels; counts
     * towards the plan's storage. Announcements have no image.
     *
     * @throws BannerHasNoImageException
     * @throws UsageLimitReachedException
     */
    public function store(UploadStorefrontImageRequest $request, Banner $banner, ChangeBannerImage $changeBannerImage): BannerResource
    {
        $changeBannerImage->handle($banner, $request->uploadedImage());

        return new BannerResource($banner->load('media'));
    }

    /**
     * Remove a banner's image.
     *
     * Needs the storefront.manage permission. Deletes the file too; shoppers
     * stop seeing the banner until it has an image again.
     */
    public function destroy(ManageBannerRequest $request, Banner $banner, RemoveBannerImage $removeBannerImage): Response
    {
        $removeBannerImage->handle($banner);

        return response()->noContent();
    }
}
