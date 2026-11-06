<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Enums\BannerKind;
use App\Tenant\Storefront\Http\Requests\ListShownBannersRequest;
use App\Tenant\Storefront\Http\Resources\PublishedBannerResource;
use App\Tenant\Storefront\Models\Banner;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The storefront's banners and announcements shown now. Open to anyone, rate-limited per visitor.
 */
final class StorefrontBannerController extends Controller
{
    /**
     * List the banners in a place.
     *
     * The image banners shown now in one place on the storefront, in their
     * order: switched on, started, not yet ended, and with their image. At
     * most 10. Texts in the language asked for, or the store's default.
     */
    public function index(ListShownBannersRequest $request): AnonymousResourceCollection
    {
        $banners = Banner::query()
            ->shownAt(CarbonImmutable::now())
            ->where('kind', BannerKind::Image->value)
            ->where('position', $request->position())
            ->whereHas('media', static fn ($media) => $media->where('collection_name', Banner::IMAGE))
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(config()->integer('storefront.banners_per_position'))
            ->get();

        return PublishedBannerResource::collection($banners);
    }

    /**
     * List the announcements.
     *
     * The messages for the bar along the top shown now, in their order: at
     * most 3. Texts in the language asked for, or the store's default.
     */
    public function announcements(): AnonymousResourceCollection
    {
        $announcements = Banner::query()
            ->shownAt(CarbonImmutable::now())
            ->where('kind', BannerKind::Announcement->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(config()->integer('storefront.announcements_shown'))
            ->get();

        return PublishedBannerResource::collection($announcements);
    }
}
