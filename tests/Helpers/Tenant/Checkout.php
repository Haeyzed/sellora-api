<?php

declare(strict_types=1);

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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

/**
 * Sends a request to a store's checkout API, under /api/v1/checkout: as a guest with a cart token, as a signed-in customer, or with neither.
 *
 * @param  array<string, mixed>  $data
 */
function checkoutRequest(string $method, string $path = '', array $data = [], ?string $token = null, ?Customer $as = null, string $subdomain = 'cart-store', string $ip = '203.0.113.10'): TestResponse
{
    forgetSignIns();
    // Other helpers can leave a staff bearer token behind.
    test()->flushHeaders();
    $request = test()->withServerVariables(['REMOTE_ADDR' => $ip]);

    if ($token !== null) {
        $request = $request->withHeader(CartToken::HEADER, $token);
    }

    if ($as !== null) {
        $request = $request->withToken(customerTokenFor($as, $subdomain));
    }

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/checkout'.$path), $data);
    test()->flushHeaders();
    test()->withServerVariables([]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * The codes of the issues a checkout response lists.
 *
 * @return list<string>
 */
function checkoutIssues(TestResponse $response): array
{
    return array_column($response->json('data.issues'), 'code');
}

/**
 * A guest cart in the cart store holding the shipped lines given, as [price, quantity], and its token.
 *
 * @param  list<array{int, int}>  $lines
 */
function checkoutCart(array $lines = [[10000, 1]]): string
{
    return shippingCart(array_map(static fn (array $line): array => [$line[0], $line[1], true], $lines));
}
