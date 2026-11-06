<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\MergeIntoWishlist;
use App\Tenant\Storefront\Exceptions\WishlistFullException;
use App\Tenant\Storefront\Http\Requests\MergeIntoWishlistRequest;
use Illuminate\Http\Response;

/**
 * Bringing a guest's wishlist, kept in the browser, into the account they sign in to.
 */
final class WishlistMergeController extends Controller
{
    /**
     * Merge a guest's wishlist.
     *
     * Send everything the shopper saved as a guest, right after they sign
     * in. What is already saved is kept as it is, and sending the same list
     * again changes nothing. An entry for a product (or variant) the store no
     * longer sells is skipped. When the new items wouldn't all fit within
     * storefront.wishlist_max_items (500), none are added (wishlist_full),
     * so the storefront can tell the shopper and keep their list.
     *
     * @throws WishlistFullException
     */
    public function __invoke(MergeIntoWishlistRequest $request, MergeIntoWishlist $mergeIntoWishlist): Response
    {
        $mergeIntoWishlist->handle($request->customer(), $request->entries());

        return response()->noContent();
    }
}
