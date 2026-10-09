<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\ChangeCategoryImage;
use App\Tenant\Catalog\Actions\RemoveCategoryImage;
use App\Tenant\Catalog\Http\Requests\ManageCategoryRequest;
use App\Tenant\Catalog\Http\Requests\UploadCatalogImageRequest;
use App\Tenant\Catalog\Http\Resources\CategoryResource;
use App\Tenant\Catalog\Models\Category;
use Illuminate\Http\Response;

/**
 * A category's image.
 */
final class CategoryImageController extends Controller
{
    /**
     * Set a category's image.
     *
     * Needs the catalog.manage permission. Replaces the current image. JPEG,
     * PNG or WebP, never SVG, up to 10 MB and 6000 × 6000 pixels; counts
     * towards the plan's storage.
     *
     * @throws UsageLimitReachedException
     */
    public function store(UploadCatalogImageRequest $request, Category $category, ChangeCategoryImage $changeCategoryImage): CategoryResource
    {
        $changeCategoryImage->handle($category, $request->uploadedImage());

        return new CategoryResource($category->load('media'));
    }

    /**
     * Remove a category's image.
     *
     * Needs the catalog.manage permission. Deletes the file too.
     */
    public function destroy(ManageCategoryRequest $request, Category $category, RemoveCategoryImage $removeCategoryImage): Response
    {
        $removeCategoryImage->handle($category);

        return response()->noContent();
    }
}
