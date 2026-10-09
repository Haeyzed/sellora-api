<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\ChangeBrandLogo;
use App\Tenant\Catalog\Actions\RemoveBrandLogo;
use App\Tenant\Catalog\Http\Requests\ManageBrandRequest;
use App\Tenant\Catalog\Http\Requests\UploadCatalogImageRequest;
use App\Tenant\Catalog\Http\Resources\BrandResource;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Http\Response;

/**
 * A brand's logo.
 */
final class BrandLogoController extends Controller
{
    /**
     * Set a brand's logo.
     *
     * Needs the catalog.manage permission. Replaces the current logo. JPEG,
     * PNG or WebP, never SVG, up to 10 MB and 6000 × 6000 pixels; counts
     * towards the plan's storage.
     *
     * @throws UsageLimitReachedException
     */
    public function store(UploadCatalogImageRequest $request, Brand $brand, ChangeBrandLogo $changeBrandLogo): BrandResource
    {
        $changeBrandLogo->handle($brand, $request->uploadedImage());

        return new BrandResource($brand->load('media'));
    }

    /**
     * Remove a brand's logo.
     *
     * Needs the catalog.manage permission. Deletes the file too.
     */
    public function destroy(ManageBrandRequest $request, Brand $brand, RemoveBrandLogo $removeBrandLogo): Response
    {
        $removeBrandLogo->handle($brand);

        return response()->noContent();
    }
}
