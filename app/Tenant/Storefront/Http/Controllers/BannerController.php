<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\CreateBanner;
use App\Tenant\Storefront\Actions\DeleteBanner;
use App\Tenant\Storefront\Actions\UpdateBanner;
use App\Tenant\Storefront\Http\Requests\ListBannersRequest;
use App\Tenant\Storefront\Http\Requests\ManageBannerRequest;
use App\Tenant\Storefront\Http\Requests\StoreBannerRequest;
use App\Tenant\Storefront\Http\Requests\UpdateBannerRequest;
use App\Tenant\Storefront\Http\Requests\ViewBannerRequest;
use App\Tenant\Storefront\Http\Resources\BannerResource;
use App\Tenant\Storefront\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The storefront's image banners and announcements, for staff.
 */
final class BannerController extends Controller
{
    /**
     * List banners.
     *
     * Needs the storefront.view permission. Switched off, scheduled and
     * ended ones included, in their order. Narrow to one kind, or to the
     * image banners in one place.
     */
    public function index(ListBannersRequest $request): AnonymousResourceCollection
    {
        $banners = Banner::query()
            ->with('media')
            ->when($request->kind(), static fn ($query, $kind) => $query->where('kind', $kind->value))
            ->when($request->position(), static fn ($query, $position) => $query->where('position', $position))
            ->orderBy('kind')
            ->orderBy('position')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($request->perPage());

        return BannerResource::collection($banners);
    }

    /**
     * Add a banner.
     *
     * Needs the storefront.manage permission. An image banner needs a place
     * and is shown once its image is uploaded; an announcement is text only.
     * Times without an offset are read in the store's timezone.
     */
    public function store(StoreBannerRequest $request, CreateBanner $createBanner): JsonResponse
    {
        return (new BannerResource($createBanner->handle($request->bannerData())->load('media')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a banner.
     *
     * Needs the storefront.view permission.
     */
    public function show(ViewBannerRequest $request, Banner $banner): BannerResource
    {
        return new BannerResource($banner->load('media'));
    }

    /**
     * Change a banner.
     *
     * Needs the storefront.manage permission. Send only what changes; for
     * texts, only the languages sent change. Its kind never changes.
     * Removing the link removes its button text too.
     */
    public function update(UpdateBannerRequest $request, Banner $banner, UpdateBanner $updateBanner): BannerResource
    {
        return new BannerResource($updateBanner->handle($banner, $request->bannerData())->load('media'));
    }

    /**
     * Delete a banner.
     *
     * Needs the storefront.manage permission. Its image's file is deleted
     * too. To hide a banner for now, switch it off instead.
     */
    public function destroy(ManageBannerRequest $request, Banner $banner, DeleteBanner $deleteBanner): Response
    {
        $deleteBanner->handle($banner);

        return response()->noContent();
    }
}
