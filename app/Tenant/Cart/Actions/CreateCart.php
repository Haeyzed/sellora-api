<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Data\NewCart;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Models\Cart;
use Carbon\CarbonImmutable;

/**
 * Gives a shopper a cart: a new one with a new token for a guest, or a signed-in customer's own one, made the first time they ask.
 *
 * A guest's token is returned here once and stored only as its hash. A
 * customer has at most one cart, even when two requests ask at once.
 */
final readonly class CreateCart
{
    public function handle(Shopper $shopper): NewCart
    {
        if ($shopper->customer !== null) {
            $cart = Cart::query()->createOrFirst(['customer_id' => $shopper->customer->id], ['last_activity_at' => CarbonImmutable::now()]);

            return new NewCart($cart, null, $cart->wasRecentlyCreated);
        }

        $token = CartToken::generate();
        $cart = Cart::query()->create(['token_hash' => CartToken::hash($token), 'last_activity_at' => CarbonImmutable::now()]);

        return new NewCart($cart, $token, true);
    }
}
