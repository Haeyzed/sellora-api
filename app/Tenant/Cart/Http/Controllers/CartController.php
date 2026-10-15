<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Actions\CreateCart;
use App\Tenant\Cart\Actions\FindCart;
use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Http\CartHttp;
use App\Tenant\Cart\Http\Resources\CartResource;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;

/**
 * The shopper's cart, for storefront apps: a signed-in customer's (with their bearer token) or a guest's (with the X-Cart-Token header).
 *
 * Every response carries the cart's version as its ETag; changes that
 * replace or remove something send it back in If-Match.
 */
final class CartController extends Controller
{
    /**
     * Create a cart.
     *
     * For a guest, a new cart and its token: the token is in this response
     * only, so keep it and send it in the X-Cart-Token header from now on.
     * For a signed-in customer, their own cart (each has one), created the
     * first time: 201 when created, 200 when it already existed. Limited per
     * IP address, with a high ceiling, because a guest has nothing else to be
     * told apart by yet.
     */
    #[Response(201, "A guest's new cart with its token, or a signed-in customer's cart made now.", type: 'array{data: CartResource}')]
    public function store(Request $request, CreateCart $createCart): CartResource
    {
        $new = $createCart->handle(CartHttp::shopper($request));

        return new CartResource($new->cart->load(CartResource::relations()), $new->token);
    }

    /**
     * Get my cart.
     *
     * A guest without a cart, or with a token that finds none, gets 404
     * cart_not_found: create one first.
     *
     * @throws CartNotFoundException
     */
    #[HeaderParameter(CartToken::HEADER, "A guest's cart token, from the response that created the cart. Ignored when signed in.", type: 'string')]
    public function show(Request $request, FindCart $findCart): CartResource
    {
        return new CartResource($findCart->handle(CartHttp::shopper($request))->load(CartResource::relations()));
    }
}
