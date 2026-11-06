<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Storefront\Models\WishlistItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The saved item in the route ({item}), found only among the signed-in customer's own: another customer's is not found (404), never forbidden.
 *
 * @mixin FormRequest
 */
trait FindsOwnWishlistItem
{
    use ActsAsCustomer;

    private ?WishlistItem $ownWishlistItem = null;

    /**
     * @throws ModelNotFoundException<WishlistItem>
     */
    public function wishlistItem(): WishlistItem
    {
        $itemId = $this->route('item');

        return $this->ownWishlistItem ??= WishlistItem::query()
            ->where('customer_id', $this->customer()->id)
            ->where('public_id', is_string($itemId) ? $itemId : '')
            ->with(['customer', 'product', 'variant'])
            ->firstOrFail();
    }
}
