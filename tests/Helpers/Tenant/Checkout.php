<?php

declare(strict_types=1);

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Models\StockReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\Process;

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
 * @param  array<string, string>  $headers
 */
function checkoutRequest(string $method, string $path = '', array $data = [], ?string $token = null, ?Customer $as = null, string $subdomain = 'cart-store', string $ip = '203.0.113.10', array $headers = []): TestResponse
{
    forgetSignIns();
    // Other helpers can leave a staff bearer token behind.
    test()->flushHeaders();
    $request = test()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders($headers);

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

/*
|--------------------------------------------------------------------------
| For CheckoutPlacementTest.php and CheckoutContentionTest.php
|--------------------------------------------------------------------------
*/

/**
 * Starts checking out the cart and fills in everything an order needs: a guest's email, the test's standard shipping method to a Lagos address, and a bank transfer (turned on by the test), with the changes given on top. Returns its ID and quote version.
 *
 * @param  array<string, mixed>  $changes
 * @return array{id: string, version: int}
 */
function readyCheckout(?string $token = null, ?Customer $as = null, array $changes = []): array
{
    checkoutRequest('POST', token: $token, as: $as)->assertCreated();
    $ready = checkoutRequest('PATCH', '', [
        ...($as === null ? ['contact' => ['email' => 'ada@example.com']] : []),
        'shipping_method' => test()->standard,
        'shipping_address' => lagosAddress(),
        'payment_method' => 'bank_transfer',
        ...$changes,
    ], token: $token, as: $as)->assertOk();

    expect(checkoutIssues($ready))->toBe([]);

    return ['id' => $ready->json('data.id'), 'version' => $ready->json('data.version')];
}

/**
 * Places the order for a checkout, with an Idempotency-Key (unless null).
 */
function placeCheckout(string $checkoutId, int $version, ?string $token = null, ?Customer $as = null, ?string $key = 'place-order-0001', ?string $returnUrl = null): TestResponse
{
    return checkoutRequest('POST', '/orders', array_filter(['checkout' => $checkoutId, 'version' => $version, 'return_url' => $returnUrl], static fn (mixed $value): bool => $value !== null), token: $token, as: $as, headers: $key === null ? [] : ['Idempotency-Key' => $key]);
}

/**
 * Two guests' checkouts, ready to place, for one of each of two variants: the first guest's cart lists them in the order given, the second's the other way round.
 *
 * @return list<array{token: string, checkout: string, version: int, key: string}>
 */
function oppositeOrderCheckouts(string $firstVariant, string $secondVariant): array
{
    $checkouts = [];

    foreach ([['ada@example.com', [$firstVariant, $secondVariant]], ['grace@example.com', [$secondVariant, $firstVariant]]] as $index => [$email, $variants]) {
        $token = newGuestCart();

        foreach ($variants as $variant) {
            cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], token: $token)->assertOk();
        }

        $ready = readyCheckout($token, changes: ['contact' => ['email' => $email]]);
        $checkouts[] = ['token' => $token, 'checkout' => $ready['id'], 'version' => $ready['version'], 'key' => "opposite-order-000{$index}"];
    }

    return $checkouts;
}

/**
 * The stock reserved in the test store for each variant given, by public ID.
 *
 * @param  list<string>  $variants
 * @return array<string, int>
 */
function reservedStock(array $variants): array
{
    return test()->store->run(static fn (): array => collect($variants)->mapWithKeys(static fn (string $variant): array => [
        $variant => (int) StockReservation::query()->where('product_variant_id', ProductVariant::query()->where('public_id', $variant)->value('id'))->sum('quantity'),
    ])->all());
}

/**
 * Places orders for several guest checkouts at the same moment, each from a PHP process of its own (as separate requests would be), and returns each response's status and body, in the order given.
 *
 * @param  list<array{token: string, checkout: string, version: int, key: string}>  $placements
 * @return list<array{status: int, body: array<string, mixed>}>
 */
function placeAtOnce(array $placements, string $subdomain = 'cart-store'): array
{
    $startAt = microtime(true) + 4;
    $processes = [];
    $connection = config()->string('database.default');
    // A parallel test process works in databases of its own (TestCase): the placing processes must use the same ones.
    $databases = [
        "database.connections.{$connection}.database" => config()->string("database.connections.{$connection}.database"),
        'tenancy.database.prefix' => config()->string('tenancy.database.prefix'),
    ];

    foreach ($placements as $index => $placement) {
        $specPath = storage_path("framework/testing/place-order-{$index}.json");
        File::ensureDirectoryExists(dirname($specPath));
        File::put($specPath, (string) json_encode([
            'url' => storeUrl($subdomain, '/api/v1/checkout/orders'),
            'headers' => [CartToken::HEADER => $placement['token'], 'Idempotency-Key' => $placement['key']],
            'body' => ['checkout' => $placement['checkout'], 'version' => $placement['version']],
            'start_at' => $startAt,
            'config' => $databases,
        ]));

        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/Checkout/place-order-process.php'), $specPath], base_path(), timeout: 60);
        $process->start();
        $processes[] = [$process, $specPath];
    }

    return array_map(static function (array $running): array {
        [$process, $specPath] = $running;
        $process->wait();
        File::delete($specPath);
        $result = json_decode($process->getOutput(), true);

        return is_array($result) ? $result : throw new LogicException('A placing process failed: '.$process->getErrorOutput().$process->getOutput());
    }, $processes);
}
