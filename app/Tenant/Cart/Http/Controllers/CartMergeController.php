<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Actions\MergeGuestCart;
use App\Tenant\Cart\Actions\PriceCart;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Http\CartHttp;
use App\Tenant\Cart\Http\Resources\CartResource;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use LogicException;

/**
 * Bringing a guest's cart into the customer's own once they sign in.
 */
final class CartMergeController extends Controller
{
    /**
     * Merge my guest cart into my cart.
     *
     * Storefront apps call this right after a shopper signs in or creates an
     * account, with the bearer token just issued and the guest cart's token in
     * X-Cart-Token, so nothing they put in their cart as a guest is lost.
     * Without a cart of their own, the guest cart becomes theirs. Otherwise a
     * variant in both keeps the larger quantity; the guest's other lines come
     * across while they can still be bought and there is room, and the rest
     * are listed in skipped_lines. The customer's code, currency and tax
     * country are kept (the guest's code is kept when they had none and it can
     * still be used). The guest cart is then deleted and its token stops
     * working: a second call gets 404 cart_not_found.
     *
     * @throws CartNotFoundException
     */
    public function store(Request $request, MergeGuestCart $mergeGuestCart, PriceCart $priceCart): CartResource
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        $merged = $mergeGuestCart->handle($customer, CartHttp::guestToken($request));

        return new CartResource($priceCart->handle($merged->cart), skipped: $merged->skipped);
    }
}
