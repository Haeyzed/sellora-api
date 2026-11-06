<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Models\WishlistItem;

/**
 * Takes a saved item off the customer's wishlist.
 */
final readonly class RemoveFromWishlist
{
    public function handle(WishlistItem $item): void
    {
        $item->delete();
    }
}
