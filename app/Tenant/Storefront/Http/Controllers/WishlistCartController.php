<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Exceptions\CartFullException;
use App\Tenant\Cart\Exceptions\CartLineQuantityTooHighException;
use App\Tenant\Cart\Exceptions\VariantNotAvailableException;
use App\Tenant\Cart\Exceptions\VariantOutOfStockException;
use App\Tenant\Storefront\Actions\MoveWishlistItemToCart;
use App\Tenant\Storefront\Http\Requests\MoveWishlistItemToCartRequest;
use Illuminate\Http\Response;

/**
 * Moving a saved item from the wishlist into the customer's cart.
 */
final class WishlistCartController extends Controller
{
    /**
     * Move an item to the cart.
     *
     * Puts the quantity given (whole units) of the item's variant in the
     * customer's cart, made if they have none yet, then takes it off the
     * wishlist. An item saved without a variant needs one chosen (variant_id).
     * The cart's own rules apply: a variant not for sale or out of stock, a
     * full cart or too many of one variant are refused, and the item stays
     * saved. Read the cart again for what it holds now.
     *
     * @throws VariantNotAvailableException
     * @throws VariantOutOfStockException
     * @throws CartFullException
     * @throws CartLineQuantityTooHighException
     */
    public function __invoke(MoveWishlistItemToCartRequest $request, string $item, MoveWishlistItemToCart $moveWishlistItemToCart): Response
    {
        $moveWishlistItemToCart->handle($request->wishlistItem(), $request->chosenVariant(), $request->quantity());

        return response()->noContent();
    }
}
