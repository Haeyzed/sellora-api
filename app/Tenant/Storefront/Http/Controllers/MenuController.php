<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\CreateMenu;
use App\Tenant\Storefront\Actions\DeleteMenu;
use App\Tenant\Storefront\Actions\UpdateMenu;
use App\Tenant\Storefront\Exceptions\MenuKeyTakenException;
use App\Tenant\Storefront\Http\Requests\ListMenusRequest;
use App\Tenant\Storefront\Http\Requests\ManageMenuRequest;
use App\Tenant\Storefront\Http\Requests\StoreMenuRequest;
use App\Tenant\Storefront\Http\Requests\UpdateMenuRequest;
use App\Tenant\Storefront\Http\Requests\ViewMenuRequest;
use App\Tenant\Storefront\Http\Resources\MenuResource;
use App\Tenant\Storefront\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's storefront menus, such as its main menu and its footer, for staff.
 */
final class MenuController extends Controller
{
    /**
     * List menus.
     *
     * Needs the storefront.view permission. Sorted by key, without their
     * links; show one menu for its links.
     */
    public function index(ListMenusRequest $request): AnonymousResourceCollection
    {
        return MenuResource::collection(Menu::query()->orderBy('key')->paginate($request->perPage()));
    }

    /**
     * Add a menu.
     *
     * Needs the storefront.manage permission. It starts with no links; set
     * them with the menu's items endpoint.
     *
     * @throws MenuKeyTakenException
     */
    public function store(StoreMenuRequest $request, CreateMenu $createMenu): JsonResponse
    {
        return (new MenuResource($createMenu->handle($request->menuData())->load(MenuResource::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a menu.
     *
     * Needs the storefront.view permission. Every link, including those
     * shoppers can't see yet (to a draft page, say) or any more (to
     * something in the trash).
     */
    public function show(ViewMenuRequest $request, Menu $menu): MenuResource
    {
        return new MenuResource($menu->load(MenuResource::RELATIONS));
    }

    /**
     * Change a menu.
     *
     * Needs the storefront.manage permission. Send only what changes. A
     * storefront still asking for the old key stops finding the menu.
     *
     * @throws MenuKeyTakenException
     */
    public function update(UpdateMenuRequest $request, Menu $menu, UpdateMenu $updateMenu): MenuResource
    {
        return new MenuResource($updateMenu->handle($menu, $request->menuData())->load(MenuResource::RELATIONS));
    }

    /**
     * Delete a menu.
     *
     * Needs the storefront.manage permission. Its links go with it; nothing
     * they go to is touched. This can't be undone.
     */
    public function destroy(ManageMenuRequest $request, Menu $menu, DeleteMenu $deleteMenu): Response
    {
        $deleteMenu->handle($menu);

        return response()->noContent();
    }
}
