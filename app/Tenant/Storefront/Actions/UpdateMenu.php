<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Data\MenuData;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\MenuKeyTakenException;
use App\Tenant\Storefront\Models\Menu;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Renames a menu or changes its key. A storefront still asking for the old key stops finding it.
 */
final readonly class UpdateMenu
{
    /**
     * @throws MenuKeyTakenException When another menu already uses the new key.
     */
    public function handle(Menu $menu, MenuData $changes): Menu
    {
        if ($changes->key !== null) {
            $menu->key = $changes->key;
        }

        if ($changes->name !== null) {
            $menu->name = $changes->name;
        }

        try {
            $menu->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new MenuKeyTakenException(previous: $exception);
        }

        StorefrontContentChanged::dispatch();

        return $menu;
    }
}
