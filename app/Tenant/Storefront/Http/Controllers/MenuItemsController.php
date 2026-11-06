<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\ReplaceMenuItems;
use App\Tenant\Storefront\Exceptions\MenuLinkTargetNotFoundException;
use App\Tenant\Storefront\Http\Requests\ReplaceMenuItemsRequest;
use App\Tenant\Storefront\Http\Resources\MenuResource;
use App\Tenant\Storefront\Models\Menu;

/**
 * A menu's links, replaced together, for staff.
 */
final class MenuItemsController extends Controller
{
    /**
     * Replace a menu's links.
     *
     * Needs the storefront.manage permission. Send every link, in order:
     * those left out are removed. A menu is two levels deep, with at most
     * 100 links in all. A link may go to a draft page or a product shoppers
     * can't see yet; the storefront shows it once they can. A link to
     * something missing or in the trash is refused with
     * menu_link_target_not_found, pointing at that link.
     *
     * @throws MenuLinkTargetNotFoundException
     */
    public function update(ReplaceMenuItemsRequest $request, Menu $menu, ReplaceMenuItems $replaceMenuItems): MenuResource
    {
        $replaceMenuItems->handle($menu, $request->menuItems());

        return new MenuResource($menu->load(MenuResource::RELATIONS));
    }
}
