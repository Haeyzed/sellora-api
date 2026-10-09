<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\CreateBrand;
use App\Tenant\Catalog\Actions\TrashBrand;
use App\Tenant\Catalog\Actions\UpdateBrand;
use App\Tenant\Catalog\Exceptions\BrandSlugTakenException;
use App\Tenant\Catalog\Http\Requests\ListBrandsRequest;
use App\Tenant\Catalog\Http\Requests\ManageBrandRequest;
use App\Tenant\Catalog\Http\Requests\StoreBrandRequest;
use App\Tenant\Catalog\Http\Requests\UpdateBrandRequest;
use App\Tenant\Catalog\Http\Requests\ViewBrandRequest;
use App\Tenant\Catalog\Http\Resources\BrandResource;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's brands, such as "Adidas", for staff managing the catalog.
 */
final class BrandController extends Controller
{
    /**
     * List brands.
     *
     * Needs the catalog.view permission. Sorted by slug, which follows the
     * name in the store's default language. Send trashed=true for the brands
     * in the trash instead.
     */
    public function index(ListBrandsRequest $request): AnonymousResourceCollection
    {
        $brands = Brand::query()
            ->with('media')
            ->when($request->wantsTrashed(), static fn ($query) => $query->onlyTrashed())
            ->orderBy('slug')
            ->orderBy('id')
            ->cursorPaginate($request->perPage());

        return BrandResource::collection($brands);
    }

    /**
     * Add a brand.
     *
     * Needs the catalog.manage permission. The name must include the store's
     * default language. Without a slug, one is made from that name.
     *
     * @throws BrandSlugTakenException
     */
    public function store(StoreBrandRequest $request, CreateBrand $createBrand): JsonResponse
    {
        return (new BrandResource($createBrand->handle($request->brandData())->load('media')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a brand.
     *
     * Needs the catalog.view permission. Also shows a brand in the trash.
     */
    public function show(ViewBrandRequest $request, Brand $brand): BrandResource
    {
        return new BrandResource($brand->load('media'));
    }

    /**
     * Change a brand.
     *
     * Needs the catalog.manage permission. Send only what changes; for texts,
     * only the languages sent change. Renaming doesn't change the slug. A
     * brand in the trash must be restored first.
     *
     * @throws BrandSlugTakenException
     */
    public function update(UpdateBrandRequest $request, Brand $brand, UpdateBrand $updateBrand): BrandResource
    {
        return new BrandResource($updateBrand->handle($brand, $request->changes())->load('media'));
    }

    /**
     * Move a brand to the trash.
     *
     * Needs the catalog.manage permission. Customers stop seeing it; products
     * and old orders keep it, and it can be restored.
     */
    public function destroy(ManageBrandRequest $request, Brand $brand, TrashBrand $trashBrand): Response
    {
        $trashBrand->handle($brand);

        return response()->noContent();
    }
}
