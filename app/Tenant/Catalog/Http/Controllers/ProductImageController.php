<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Catalog\Actions\AddProductImage;
use App\Tenant\Catalog\Actions\RemoveProductImage;
use App\Tenant\Catalog\Http\Requests\ManageProductRequest;
use App\Tenant\Catalog\Http\Requests\UploadCatalogImageRequest;
use App\Tenant\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A product's gallery of images.
 */
final class ProductImageController extends Controller
{
    /**
     * Add an image.
     *
     * Needs the catalog.manage permission. Goes at the end of the gallery; the
     * first image is shown in lists. JPEG, PNG or WebP, never SVG, up to 10 MB
     * and 6000 × 6000 pixels. Counts towards the plan's storage. Smaller copies
     * are made shortly after; their URLs are null until then.
     *
     * @throws UsageLimitReachedException
     */
    public function store(UploadCatalogImageRequest $request, Product $product, AddProductImage $addProductImage): JsonResponse
    {
        return (new StorefrontImageResource($addProductImage->handle($product, $request->uploadedImage())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Delete an image.
     *
     * Needs the catalog.manage permission. Deletes the file too; a variant that
     * showed it shows none.
     */
    public function destroy(ManageProductRequest $request, Product $product, string $image, RemoveProductImage $removeProductImage): Response
    {
        $removeProductImage->handle($product->getMedia(Product::GALLERY)->firstWhere('uuid', $image) ?? abort(Response::HTTP_NOT_FOUND));

        return response()->noContent();
    }
}
