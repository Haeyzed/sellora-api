<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Storefront\Actions\AddToWishlist;
use App\Tenant\Storefront\Actions\RemoveFromWishlist;
use App\Tenant\Storefront\Exceptions\WishlistFullException;
use App\Tenant\Storefront\Http\Requests\AddToWishlistRequest;
use App\Tenant\Storefront\Http\Requests\ListWishlistRequest;
use App\Tenant\Storefront\Http\Requests\RemoveFromWishlistRequest;
use App\Tenant\Storefront\Http\Resources\WishlistItemList;
use App\Tenant\Storefront\Http\Resources\WishlistItemResource;
use App\Tenant\Storefront\Models\WishlistItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The signed-in customer's wishlist: what they saved to buy later. Customers only, each their own.
 *
 * A wishlist holds no quantity, price or stock, keeps items until the
 * customer removes them, and holds at most storefront.wishlist_max_items
 * (500). A guest's list is kept by the storefront in the browser and merged
 * in when they sign in.
 */
final class WishlistController extends Controller
{
    public function __construct(private readonly WishlistItemList $wishlistItemList) {}

    /**
     * List the wishlist.
     *
     * Newest first, paged by cursor. Each item says whether it is still for
     * sale, its price now in the currency asked for (?currency=), and whether
     * it can be bought now; an item no longer sold stays, with no price,
     * until the customer removes it. Quantities are never shown.
     */
    public function index(ListWishlistRequest $request): AnonymousResourceCollection
    {
        $page = WishlistItem::query()->where('customer_id', $request->customer()->id)->with(WishlistItemResource::relations())
            ->orderByDesc('id')->cursorPaginate($request->perPage());

        return WishlistItemResource::collection($page->setCollection(collect($this->wishlistItemList->resources($page->items(), $request->requestedCurrency()))));
    }

    /**
     * Save an item.
     *
     * A product customers can buy (out of stock is fine), and optionally one
     * of its variants. Saving what is already saved changes nothing and
     * answers 200 with it; a new item answers 201. Refused with wishlist_full
     * once the wishlist holds storefront.wishlist_max_items (500).
     *
     * @throws WishlistFullException
     */
    public function store(AddToWishlistRequest $request, AddToWishlist $addToWishlist): WishlistItemResource
    {
        $item = $addToWishlist->handle($request->customer(), $request->entry());

        return $this->wishlistItemList->resources([$item->load(WishlistItemResource::relations())], null)[0];
    }

    /**
     * Remove an item.
     *
     * Only the customer's own; another customer's is not found.
     */
    public function destroy(RemoveFromWishlistRequest $request, string $item, RemoveFromWishlist $removeFromWishlist): Response
    {
        $removeFromWishlist->handle($request->wishlistItem());

        return response()->noContent();
    }
}
