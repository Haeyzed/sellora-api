<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\ChangeShareImage;
use App\Tenant\Storefront\Actions\RemoveShareImage;
use App\Tenant\Storefront\Http\Requests\ManageStorefrontRequest;
use App\Tenant\Storefront\Http\Requests\UploadStorefrontImageRequest;
use App\Tenant\Storefront\Http\Resources\StorefrontSettingsResource;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Http\Response;

/**
 * The image social sites show when a storefront page is shared.
 */
final class ShareImageController extends Controller
{
    /**
     * Set the share image.
     *
     * Needs the storefront.manage permission. Replaces the current one. JPEG,
     * PNG or WebP, never SVG, up to 10 MB and 6000 × 6000 pixels; counts
     * towards the plan's storage.
     *
     * @throws UsageLimitReachedException
     */
    public function store(UploadStorefrontImageRequest $request, ChangeShareImage $changeShareImage): StorefrontSettingsResource
    {
        $changeShareImage->handle($request->uploadedImage());

        return new StorefrontSettingsResource(StorefrontSettings::current()->load('media'));
    }

    /**
     * Remove the share image.
     *
     * Needs the storefront.manage permission. Deletes the file too.
     */
    public function destroy(ManageStorefrontRequest $request, RemoveShareImage $removeShareImage): Response
    {
        $removeShareImage->handle();

        return response()->noContent();
    }
}
