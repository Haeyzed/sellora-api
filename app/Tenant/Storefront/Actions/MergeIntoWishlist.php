<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Storefront\Data\WishlistEntryData;
use App\Tenant\Storefront\Exceptions\WishlistFullException;
use App\Tenant\Storefront\Services\WishlistEntries;
use Illuminate\Support\Facades\DB;

/**
 * Adds the list a shopper kept in their browser as a guest to their wishlist once they sign in. Sending it again changes nothing.
 *
 * Entries already saved are kept as they are. When the new ones wouldn't
 * all fit within storefront.wishlist_max_items, none are added, so the
 * storefront can tell the shopper and keep their list.
 */
final readonly class MergeIntoWishlist
{
    public function __construct(private WishlistEntries $wishlistEntries) {}

    /**
     * @param  list<WishlistEntryData>  $entries
     *
     * @throws WishlistFullException
     */
    public function handle(Customer $customer, array $entries): void
    {
        DB::transaction(fn (): array => $this->wishlistEntries->save($customer, $entries));
    }
}
