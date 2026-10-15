<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Cart\CartToken;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For the tests in tests/Feature/Tenant/Cart
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to a store's cart API, under /api/v1/cart: as a guest with a cart token, as a signed-in customer (or with any bearer token), or with neither.
 *
 * @param  array<string, mixed>  $data
 */
function cartRequest(string $method, string $path = '', array $data = [], ?string $token = null, Customer|string|null $as = null, ?string $ifMatch = null, string $subdomain = 'cart-store', string $ip = '203.0.113.10'): TestResponse
{
    forgetSignIns();
    $headers = array_filter([CartToken::HEADER => $token, 'If-Match' => $ifMatch], static fn (?string $value): bool => $value !== null);
    $bearer = $as instanceof Customer ? customerTokenFor($as, $subdomain) : $as;
    $request = test()->withHeaders($headers)->withServerVariables(['REMOTE_ADDR' => $ip]);

    if ($bearer !== null) {
        $request = $request->withToken($bearer);
    }

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/cart'.$path), $data);
    test()->flushHeaders();
    test()->withServerVariables([]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * A new guest cart's token, from the response that created it.
 */
function newGuestCart(string $subdomain = 'cart-store', string $ip = '203.0.113.10'): string
{
    $token = cartRequest('POST', subdomain: $subdomain, ip: $ip)->assertCreated()->json('data.token');

    return is_string($token) ? $token : throw new LogicException('No token came back.');
}

/**
 * A published product's variant priced in the store's base currency: in stock when its stock isn't counted (the default), or with the stock given.
 */
function cartVariant(int $price = 1000, ?int $stock = null, bool $backorder = false, string $name = 'Linen shirt'): ProductVariant
{
    $currency = app(FindStoreSettings::class)->handle()->currency_code;
    $product = Product::factory()->create(['name' => ['en' => $name]]);
    $product->forceFill(['status' => ProductStatus::Active])->save();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => Money::ofMinor($price, $currency), 'currency' => $currency]);

    app(ChangeInventorySettings::class)->handle($variant, new InventorySettingsData(tracksStock: $stock !== null, allowsBackorder: $backorder));

    if ($stock !== null && $stock > 0) {
        app(ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), $stock);
    }

    return $variant;
}
