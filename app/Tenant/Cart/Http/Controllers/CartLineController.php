<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Actions\AddCartLine;
use App\Tenant\Cart\Actions\ChangeCartLineQuantity;
use App\Tenant\Cart\Actions\EmptyCart;
use App\Tenant\Cart\Actions\RemoveCartLine;
use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartFullException;
use App\Tenant\Cart\Exceptions\CartLineQuantityTooHighException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Exceptions\VariantNotAvailableException;
use App\Tenant\Cart\Exceptions\VariantOutOfStockException;
use App\Tenant\Cart\Http\CartHttp;
use App\Tenant\Cart\Http\Requests\AddCartLineRequest;
use App\Tenant\Cart\Http\Requests\ChangeCartLineRequest;
use App\Tenant\Cart\Http\Resources\CartResource;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\Request;

/**
 * The lines of the shopper's cart: a signed-in customer's (with their bearer token) or a guest's (with the X-Cart-Token header).
 *
 * Each answer is the whole cart, with its new version as the ETag. Changing
 * or removing lines needs that version in If-Match, so an edit made in one
 * tab never undoes one made in another: an out-of-date version gets 412
 * cart_changed, and the change isn't made.
 */
final class CartLineController extends Controller
{
    /**
     * Add to my cart.
     *
     * Adds to the line already there for the variant, or starts one. Only a
     * variant that is for sale and not out of stock (backorders are fine) can
     * go in; how many are left is never said, and is checked when checking
     * out. Needs no If-Match: adds simply add up.
     *
     * @throws CartNotFoundException
     * @throws VariantNotAvailableException
     * @throws VariantOutOfStockException
     * @throws CartFullException
     * @throws CartLineQuantityTooHighException
     */
    #[HeaderParameter(CartToken::HEADER, "A guest's cart token, from the response that created the cart. Ignored when signed in.", type: 'string')]
    public function store(AddCartLineRequest $request, AddCartLine $addCartLine): CartResource
    {
        $cart = $addCartLine->handle(CartHttp::shopper($request), $request->variant(), $request->quantity());

        return new CartResource($cart->load(CartResource::relations()));
    }

    /**
     * Change a line's quantity.
     *
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    #[HeaderParameter(CartToken::HEADER, "A guest's cart token, from the response that created the cart. Ignored when signed in.", type: 'string')]
    #[HeaderParameter('If-Match', 'The cart version (ETag) this change was made from.', type: 'string', example: '"7"')]
    public function update(ChangeCartLineRequest $request, ChangeCartLineQuantity $changeCartLineQuantity, string $line): CartResource
    {
        $cart = $changeCartLineQuantity->handle(CartHttp::shopper($request), $line, $request->quantity(), CartHttp::expectedVersion($request));

        return new CartResource($cart->load(CartResource::relations()));
    }

    /**
     * Remove a line.
     *
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    #[HeaderParameter(CartToken::HEADER, "A guest's cart token, from the response that created the cart. Ignored when signed in.", type: 'string')]
    #[HeaderParameter('If-Match', 'The cart version (ETag) this change was made from.', type: 'string', example: '"7"')]
    public function destroy(Request $request, RemoveCartLine $removeCartLine, string $line): CartResource
    {
        $cart = $removeCartLine->handle(CartHttp::shopper($request), $line, CartHttp::expectedVersion($request));

        return new CartResource($cart->load(CartResource::relations()));
    }

    /**
     * Empty my cart.
     *
     * Removes every line; the cart and its token stay.
     *
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    #[HeaderParameter(CartToken::HEADER, "A guest's cart token, from the response that created the cart. Ignored when signed in.", type: 'string')]
    #[HeaderParameter('If-Match', 'The cart version (ETag) this change was made from.', type: 'string', example: '"7"')]
    public function destroyAll(Request $request, EmptyCart $emptyCart): CartResource
    {
        $cart = $emptyCart->handle(CartHttp::shopper($request), CartHttp::expectedVersion($request));

        return new CartResource($cart->load(CartResource::relations()));
    }
}
