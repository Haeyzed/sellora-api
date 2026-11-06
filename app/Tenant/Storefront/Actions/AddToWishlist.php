<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Storefront\Data\WishlistEntryData;
use App\Tenant\Storefront\Exceptions\WishlistFullException;
use App\Tenant\Storefront\Models\WishlistItem;
use App\Tenant\Storefront\Services\WishlistEntries;
use Illuminate\Support\Facades\DB;

/**
 * Saves a product, or one of its variants, to a signed-in customer's wishlist. Saving what is already there changes nothing.
 *
 * Out-of-stock products may be saved: a wishlist holds no quantity, price
 * or stock. A wishlist holds at most storefront.wishlist_max_items, even
 * when two saves arrive at once.
 */
final readonly class AddToWishlist
{
    public function __construct(private WishlistEntries $wishlistEntries) {}

    /**
     * @throws WishlistFullException
     */
    public function handle(Customer $customer, WishlistEntryData $entry): WishlistItem
    {
        return DB::transaction(fn (): WishlistItem => $this->wishlistEntries->save($customer, [$entry])[$entry->key()]);
    }
}
