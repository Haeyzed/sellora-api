<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\CreatePage;
use App\Tenant\Storefront\Actions\TrashPage;
use App\Tenant\Storefront\Actions\UpdatePage;
use App\Tenant\Storefront\Exceptions\PageSlugTakenException;
use App\Tenant\Storefront\Http\Requests\ListPagesRequest;
use App\Tenant\Storefront\Http\Requests\ManagePageRequest;
use App\Tenant\Storefront\Http\Requests\StorePageRequest;
use App\Tenant\Storefront\Http\Requests\UpdatePageRequest;
use App\Tenant\Storefront\Http\Requests\ViewPageRequest;
use App\Tenant\Storefront\Http\Resources\PageResource;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's own storefront pages, such as "About us", for staff.
 */
final class PageController extends Controller
{
    /**
     * List pages.
     *
     * Needs the storefront.view permission. Drafts included, sorted by slug.
     * Narrow to one status, or send trashed=true for the pages in the trash.
     */
    public function index(ListPagesRequest $request): AnonymousResourceCollection
    {
        $pages = Page::query()
            ->with('media')
            ->when($request->wantsTrashed(), static fn ($query) => $query->onlyTrashed())
            ->when($request->status(), static fn ($query, $status) => $query->where('status', $status->value))
            ->orderBy('slug')
            ->orderBy('id')
            ->paginate($request->perPage());

        return PageResource::collection($pages);
    }

    /**
     * Add a page.
     *
     * Needs the storefront.manage permission. It starts as a draft. The title
     * must include the store's default language. Without a slug, one is made
     * from that title. The body is cleaned of anything unsafe before it is
     * saved.
     *
     * @throws PageSlugTakenException
     */
    public function store(StorePageRequest $request, CreatePage $createPage): JsonResponse
    {
        return (new PageResource($createPage->handle($request->pageData())->load('media')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a page.
     *
     * Needs the storefront.view permission. Also shows a draft or a page in
     * the trash.
     */
    public function show(ViewPageRequest $request, Page $page): PageResource
    {
        return new PageResource($page->load('media'));
    }

    /**
     * Change a page.
     *
     * Needs the storefront.manage permission. Send only what changes; for
     * texts, only the languages sent change, and null for a whole field
     * (except the title) removes it. Changing the title doesn't change the
     * slug. A page in the trash must be restored first.
     *
     * @throws PageSlugTakenException
     */
    public function update(UpdatePageRequest $request, Page $page, UpdatePage $updatePage): PageResource
    {
        return new PageResource($updatePage->handle($page, $request->pageData())->load('media'));
    }

    /**
     * Move a page to the trash.
     *
     * Needs the storefront.manage permission. Shoppers stop seeing it and it
     * stops being the homepage; it can be restored.
     */
    public function destroy(ManagePageRequest $request, Page $page, TrashPage $trashPage): Response
    {
        $trashPage->handle($page);

        return response()->noContent();
    }
}
