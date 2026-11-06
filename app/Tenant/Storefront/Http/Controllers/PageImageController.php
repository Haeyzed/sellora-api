<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Storefront\Actions\AddPageImage;
use App\Tenant\Storefront\Actions\RemovePageImage;
use App\Tenant\Storefront\Http\Requests\ManagePageRequest;
use App\Tenant\Storefront\Http\Requests\UploadStorefrontImageRequest;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Images for a page's body: the only images a page may show are the store's own.
 */
final class PageImageController extends Controller
{
    /**
     * Upload an image for a page.
     *
     * Needs the storefront.manage permission. Put its address in the page's
     * body to show it. JPEG, PNG or WebP, never SVG, up to 10 MB and
     * 6000 × 6000 pixels; counts towards the plan's storage.
     *
     * @throws UsageLimitReachedException
     */
    public function store(UploadStorefrontImageRequest $request, Page $page, AddPageImage $addPageImage): JsonResponse
    {
        return (new StorefrontImageResource($addPageImage->handle($page, $request->uploadedImage())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove an image of a page.
     *
     * Needs the storefront.manage permission. Deletes the file too; a body
     * still showing it shows a broken image until it is changed.
     */
    public function destroy(ManagePageRequest $request, Page $page, string $image, RemovePageImage $removePageImage): Response
    {
        $removePageImage->handle($page, $image);

        return response()->noContent();
    }
}
