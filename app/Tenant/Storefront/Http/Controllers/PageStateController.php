<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\MakeHomepage;
use App\Tenant\Storefront\Actions\PublishPage;
use App\Tenant\Storefront\Actions\RestorePage;
use App\Tenant\Storefront\Actions\UnpublishPage;
use App\Tenant\Storefront\Exceptions\PageSlugTakenException;
use App\Tenant\Storefront\Http\Requests\ManagePageRequest;
use App\Tenant\Storefront\Http\Resources\PageResource;
use App\Tenant\Storefront\Models\Page;

/**
 * Whether a page is on the storefront, whether the storefront opens on it, and bringing it back from the trash.
 */
final class PageStateController extends Controller
{
    /**
     * Publish a page.
     *
     * Needs the storefront.manage permission. Shoppers see it on their next
     * visit. Publishing a published page changes nothing.
     */
    public function publish(ManagePageRequest $request, Page $page, PublishPage $publishPage): PageResource
    {
        return new PageResource($publishPage->handle($page)->load('media'));
    }

    /**
     * Unpublish a page.
     *
     * Needs the storefront.manage permission. It goes back to a draft; menus
     * and policy links to it stop showing.
     */
    public function unpublish(ManagePageRequest $request, Page $page, UnpublishPage $unpublishPage): PageResource
    {
        return new PageResource($unpublishPage->handle($page)->load('media'));
    }

    /**
     * Make a page the homepage.
     *
     * Needs the storefront.manage permission. The page that was the homepage
     * stops being it. Shoppers see it once it is published.
     */
    public function homepage(ManagePageRequest $request, Page $page, MakeHomepage $makeHomepage): PageResource
    {
        return new PageResource($makeHomepage->handle($page)->load('media'));
    }

    /**
     * Restore a page from the trash.
     *
     * Needs the storefront.manage permission. It comes back as it was
     * (published or a draft), but not as the homepage.
     *
     * @throws PageSlugTakenException
     */
    public function restore(ManagePageRequest $request, Page $page, RestorePage $restorePage): PageResource
    {
        return new PageResource($restorePage->handle($page)->load('media'));
    }
}
