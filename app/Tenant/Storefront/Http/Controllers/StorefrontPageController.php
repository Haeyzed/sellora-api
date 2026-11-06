<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Http\Resources\PublishedPageResource;
use App\Tenant\Storefront\Models\Page;
use App\Tenant\Storefront\Services\ServedPageBody;

/**
 * The store's published pages, for the storefront. Open to anyone, rate-limited per visitor.
 */
final class StorefrontPageController extends Controller
{
    /**
     * Show the homepage.
     *
     * The page the storefront opens on, once published; not found while
     * there is none. Texts in the language asked for, or the store's default.
     */
    public function home(ServedPageBody $servedPageBody): PublishedPageResource
    {
        $page = Page::query()->published()->where('is_homepage', true)->firstOrFail();

        return new PublishedPageResource($page, $servedPageBody->of($page, app()->getLocale()));
    }

    /**
     * Show a page.
     *
     * Found by its slug, such as "about-us". A draft or a page in the trash
     * is not found. Texts in the language asked for, or the store's default.
     */
    public function show(string $slug, ServedPageBody $servedPageBody): PublishedPageResource
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return new PublishedPageResource($page, $servedPageBody->of($page, app()->getLocale()));
    }
}
