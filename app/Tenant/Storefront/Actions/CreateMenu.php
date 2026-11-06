<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Data\MenuData;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\MenuKeyTakenException;
use App\Tenant\Storefront\Models\Menu;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * Adds an empty storefront menu, such as the footer, under the key the storefront will ask for it by.
 */
final readonly class CreateMenu
{
    /**
     * @throws MenuKeyTakenException When another menu already uses the key.
     */
    public function handle(MenuData $menuData): Menu
    {
        $menu = new Menu;
        $menu->key = $menuData->key ?? throw new LogicException('A new menu needs a key.');
        $menu->name = $menuData->name ?? throw new LogicException('A new menu needs a name.');

        try {
            $menu->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new MenuKeyTakenException(previous: $exception);
        }

        StorefrontContentChanged::dispatch();

        return $menu;
    }
}
