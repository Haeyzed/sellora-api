<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Services;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Storefront\Data\WishlistEntryData;
use App\Tenant\Storefront\Exceptions\WishlistFullException;
use App\Tenant\Storefront\Models\WishlistItem;

/**
 * Saves entries to a customer's wishlist for adding one and merging a guest's list alike, holding the wishlist's limit even when requests arrive at once.
 *
 * Call it inside a transaction: it takes a lock for the customer's wishlist
 * (an advisory lock on the customer, so it never blocks other changes to
 * the customer's account), and the count and the saving happen under it.
 */
final readonly class WishlistEntries
{
    /**
     * Saves the entries not saved yet, in order, all or none: refused when they wouldn't all fit.
     *
     * @param  list<WishlistEntryData>  $entries
     * @return array<string, WishlistItem> Every entry's item, new or already saved, by WishlistEntryData::key().
     *
     * @throws WishlistFullException
     */
    public function save(Customer $customer, array $entries): array
    {
        WishlistItem::query()->getConnection()->select('select pg_advisory_xact_lock(hashtext(?))', ['wishlist:'.$customer->id]);

        $saved = [];

        foreach (WishlistItem::query()->where('customer_id', $customer->id)->get() as $item) {
            $saved[$item->product_id.':'.($item->product_variant_id ?? '')] = $item;
        }

        $new = [];

        foreach ($entries as $entry) {
            if (! isset($saved[$entry->key()])) {
                $new[$entry->key()] = $entry;
            }
        }

        $limit = config()->integer('storefront.wishlist_max_items');

        if (count($saved) + count($new) > $limit) {
            throw new WishlistFullException($limit);
        }

        foreach ($new as $key => $entry) {
            $saved[$key] = WishlistItem::query()->create([
                'customer_id' => $customer->id,
                'product_id' => $entry->product->id,
                'product_variant_id' => $entry->variant?->id,
            ]);
        }

        return $saved;
    }
}
