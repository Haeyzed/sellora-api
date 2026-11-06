<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Models\Menu;

/**
 * Deletes a menu with all its links. Nothing it links to is touched.
 */
final readonly class DeleteMenu
{
    public function handle(Menu $menu): void
    {
        $menu->delete();

        StorefrontContentChanged::dispatch();
    }
}
