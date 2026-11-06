<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Http\Resources\PublishedMenuResource;
use App\Tenant\Storefront\Models\Menu;
use App\Tenant\Storefront\Services\ShownMenuItems;

/**
 * The store's menus, for the storefront. Open to anyone, rate-limited per visitor.
 */
final class StorefrontMenuController extends Controller
{
    /**
     * Show a menu.
     *
     * Found by its key, such as "main" or "footer"; not found when the store
     * has no such menu. Only links shoppers may follow are shown: to a
     * published page, a category or brand outside the trash, a product
     * shoppers can see, or an address. A hidden link's own links are hidden
     * with it. Labels in the language asked for, or the store's default.
     */
    public function show(string $key, ShownMenuItems $shownMenuItems): PublishedMenuResource
    {
        $menu = Menu::query()->where('key', $key)->firstOrFail();

        return new PublishedMenuResource($menu, $shownMenuItems->of($menu));
    }
}
