<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Models\Cart;

/**
 * Finds the shopper's cart, for Cart and Checkout (section 6): a customer's own, or the guest cart their token opens.
 *
 * A missing, malformed or unknown token all get the same answer, and a
 * token never opens a customer's cart.
 */
final readonly class FindCart
{
    /**
     * @throws CartNotFoundException When the shopper has no cart.
     */
    public function handle(Shopper $shopper): Cart
    {
        if ($shopper->customer !== null) {
            return Cart::query()->where('customer_id', $shopper->customer->id)->first() ?? throw new CartNotFoundException;
        }

        if ($shopper->token === null || ! CartToken::isWellFormed($shopper->token)) {
            throw new CartNotFoundException;
        }

        // A customer's cart has no token hash (carts_customer_or_guest), so a token can't open one.
        return Cart::query()->where('token_hash', CartToken::hash($shopper->token))->first() ?? throw new CartNotFoundException;
    }
}
