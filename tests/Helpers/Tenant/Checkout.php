<?php

declare(strict_types=1);

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Checkout\Models\Checkout;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| For the tests in tests/Feature/Tenant/Checkout
|--------------------------------------------------------------------------
*/

/**
 * An open checkout made straight in the current store, on a cart of its own (the customer's when one is given, else a guest's), with the fields given.
 *
 * @param  array<string, mixed>  $fields
 */
function rawCheckout(array $fields = []): Checkout
{
    $customerId = $fields['customer_id'] ?? null;
    $cart = new Cart;
    $cart->forceFill([
        'customer_id' => $customerId,
        'token_hash' => $customerId === null ? CartToken::hash(Str::random(64)) : null,
        'last_activity_at' => CarbonImmutable::now(),
    ])->save();

    $checkout = new Checkout;
    $checkout->forceFill([
        'cart_id' => $cart->id,
        'locale' => 'en',
        'last_activity_at' => CarbonImmutable::now(),
        ...$fields,
    ])->save();

    return $checkout;
}
