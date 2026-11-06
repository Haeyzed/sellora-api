<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Storefront\Data\MenuItemData;
use App\Tenant\Storefront\Enums\MenuLinkType;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\MenuLinkTargetNotFoundException;
use App\Tenant\Storefront\Models\Menu;
use App\Tenant\Storefront\Models\MenuItem;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Replaces all of a menu's links with the ones staff send, in their order, so the storefront shows exactly that menu.
 *
 * A link may go to a draft page or a product shoppers can't see yet; the
 * storefront leaves it out until they can. A link to something missing or in
 * the trash is refused. Changes to one menu queue on its row's lock, so two
 * at once never mix their links: the last one wins whole.
 */
final readonly class ReplaceMenuItems
{
    /**
     * @param  list<MenuItemData>  $items
     *
     * @throws MenuLinkTargetNotFoundException When a link goes to something the store doesn't have, or has in the trash.
     */
    public function handle(Menu $menu, array $items): Menu
    {
        DB::transaction(function () use ($menu, $items): void {
            Menu::query()->whereKey($menu->id)->lockForUpdate()->sole();

            MenuItem::query()->where('menu_id', $menu->id)->whereNull('parent_id')->delete();
            $this->addItems($menu, $items, parentId: null);

            $menu->touch();
        });

        StorefrontContentChanged::dispatch();

        return $menu;
    }

    /**
     * @param  list<MenuItemData>  $items
     */
    private function addItems(Menu $menu, array $items, ?int $parentId): void
    {
        foreach ($items as $position => $itemData) {
            $item = new MenuItem;
            $item->menu_id = $menu->id;
            $item->parent_id = $parentId;
            $item->changeTranslations('label', $itemData->label);
            $item->setAttribute($itemData->linkType->column(), $this->destination($itemData));
            $item->opens_in_new_tab = $itemData->opensInNewTab;
            $item->position = $position;
            $item->save();

            $this->addItems($menu, $itemData->children, parentId: $item->id);
        }
    }

    /**
     * What goes in the link's destination column: the address, or the database ID of the page, category, brand or product.
     *
     * @throws MenuLinkTargetNotFoundException
     */
    private function destination(MenuItemData $itemData): int|string
    {
        if ($itemData->linkType === MenuLinkType::Url) {
            return $itemData->url ?? throw new MenuLinkTargetNotFoundException($itemData->path.'.url');
        }

        $query = match ($itemData->linkType) {
            MenuLinkType::Page => Page::query(),
            MenuLinkType::Category => Category::query(),
            MenuLinkType::Brand => Brand::query(),
            MenuLinkType::Product => Product::query(),
        };

        $id = $query->where('public_id', $itemData->target)->value('id');

        return is_int($id) ? $id : throw new MenuLinkTargetNotFoundException($itemData->path.'.target');
    }
}
