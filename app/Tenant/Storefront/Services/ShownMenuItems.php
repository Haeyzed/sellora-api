<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Services;

use App\Tenant\Storefront\Enums\MenuLinkType;
use App\Tenant\Storefront\Models\Menu;
use App\Tenant\Storefront\Models\MenuItem;

/**
 * A menu's links that shoppers may see, in order: those going to a published page, a category or brand outside the trash, a product shoppers can see, or an address.
 *
 * A link whose destination is hidden is left out together with the links
 * under it, so the storefront never shows a link that leads nowhere.
 */
final readonly class ShownMenuItems
{
    /** The destinations loaded for every link; each is loaded only while shoppers may see it (the trash never is). */
    private const array SHOWN_DESTINATIONS = ['shownPage', 'shownCategory', 'shownBrand', 'shownProduct'];

    /**
     * @return list<MenuItem> Each with only its shown children loaded.
     */
    public function of(Menu $menu): array
    {
        $menu->load([
            'items.children',
            ...array_map(static fn (string $destination): string => 'items.'.$destination, self::SHOWN_DESTINATIONS),
            ...array_map(static fn (string $destination): string => 'items.children.'.$destination, self::SHOWN_DESTINATIONS),
        ]);

        $shownItems = [];

        foreach ($menu->items as $item) {
            if (! $this->isShown($item)) {
                continue;
            }

            $item->setRelation('children', $item->children->filter(fn (MenuItem $child): bool => $this->isShown($child))->values());
            $shownItems[] = $item;
        }

        return $shownItems;
    }

    private function isShown(MenuItem $item): bool
    {
        return match ($item->linkType()) {
            MenuLinkType::Page => $item->shownPage !== null,
            MenuLinkType::Category => $item->shownCategory !== null,
            MenuLinkType::Brand => $item->shownBrand !== null,
            MenuLinkType::Product => $item->shownProduct !== null,
            MenuLinkType::Url => true,
        };
    }
}
