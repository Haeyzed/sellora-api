<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Features\Features;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\AdjustStock;
use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Enums\AdjustmentReason;
use App\Tenant\Inventory\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For BrandTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $data
 */
function brandRequest(string $method, string $path = '', array $data = [], ?StaffMember $as = null, string $subdomain = 'brand-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'brand-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog/brands'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For CategoryTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $data
 */
function categoryRequest(string $method, string $path = '', array $data = [], ?StaffMember $as = null, string $subdomain = 'category-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'category-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog/categories'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Adds a category through the API and returns its public ID.
 */
function addCategory(string $name, ?string $parent = null): string
{
    return categoryRequest('POST', data: ['name' => ['en' => $name], 'parent' => $parent])->assertCreated()->json('data.id');
}

/*
|--------------------------------------------------------------------------
| For ProductLifecycleTest.php
|--------------------------------------------------------------------------
*/

function lifecycleProductLimit(int $limit): void
{
    test()->featureSource->give(test()->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['products' => $limit]));
    app(Features::class)->forget(test()->store->getTenantKey());
}

/**
 * @param  array<string, mixed>  $data
 */
function lifecycleRequest(string $method, string $path, array $data = []): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue(test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('lifecycle-store', '/api/v1/staff/catalog'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @param  array<string, mixed>  $variant
 * @return array{product: string, variant: string}
 */
function lifecycleProduct(string $name = 'Linen shirt', array $variant = ['price' => 1999]): array
{
    $response = lifecycleRequest('POST', '/products', ['name' => ['en' => $name], 'variant' => ['weight_grams' => 250, ...$variant]])->assertCreated();

    return ['product' => $response->json('data.id'), 'variant' => $response->json('data.variants.0.id')];
}

/*
|--------------------------------------------------------------------------
| For ProductTest.php
|--------------------------------------------------------------------------
*/

function allowProducts(string $tenantId, int $limit): void
{
    test()->featureSource->give($tenantId, FakeFeatureSource::snapshot(limits: ['products' => $limit]));
    app(Features::class)->forget($tenantId);
}

/**
 * @param  array<string, mixed>  $data
 */
function productRequest(string $method, string $path = '', array $data = [], ?StaffMember $as = null, string $subdomain = 'product-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'product-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog/products'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  array<string, mixed>  $variant
 * @return array<string, mixed>
 */
function newProduct(array $overrides = [], array $variant = []): array
{
    return [
        'name' => ['en' => 'Linen shirt'],
        'variant' => ['price' => 1999, 'weight_grams' => 250, ...$variant],
        ...$overrides,
    ];
}

/*
|--------------------------------------------------------------------------
| For ProductVariantsTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $data
 */
function catalogRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'variant-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'variant-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Creates an attribute with values through the API and returns its ID and the value IDs by English label.
 *
 * @param  list<string>  $labels
 * @return array{id: string, values: array<string, string>}
 */
function attributeWithValues(string $name, array $labels): array
{
    $response = catalogRequest('POST', '/attributes', [
        'name' => ['en' => $name],
        'values' => array_map(static fn (string $label): array => ['en' => $label], $labels),
    ])->assertCreated();

    $values = [];
    foreach ($response->json('data.values') as $value) {
        $values[$value['label']['en']] = $value['id'];
    }

    return ['id' => $response->json('data.id'), 'values' => $values];
}

/**
 * Creates a product with one variant through the API and returns the product and variant IDs.
 *
 * @return array{product: string, variant: string}
 */
function productWithOneVariant(string $sku = 'TEE-1'): array
{
    $response = catalogRequest('POST', '/products', [
        'name' => ['en' => 'Linen shirt'],
        'variant' => ['price' => 1999, 'weight_grams' => 250, 'sku' => $sku],
    ])->assertCreated();

    return ['product' => $response->json('data.id'), 'variant' => $response->json('data.variants.0.id')];
}

/*
|--------------------------------------------------------------------------
| For StorefrontCatalogTest.php
|--------------------------------------------------------------------------
*/

function storefrontRequest(string $path, ?string $acceptLanguage = null, string $subdomain = 'shop-front'): TestResponse
{
    forgetSignIns();
    $response = test()->withHeaders(array_filter(['Accept-Language' => $acceptLanguage]))->getJson(storeUrl($subdomain, '/api/v1/catalog'.$path));
    tenancy()->end();

    return $response;
}

/**
 * Makes a product in the current store with one variant per given price (null for an unpriced variant).
 *
 * @param  array<string, string>  $name
 * @param  list<int|null>  $prices
 * @param  array<string, mixed>  $attributes
 */
function storefrontProduct(array $name, ProductStatus $status = ProductStatus::Active, array $prices = [1999], array $attributes = []): Product
{
    $product = Product::factory()->create(['name' => $name, ...$attributes]);
    $product->forceFill(['status' => $status])->save();

    foreach ($prices as $position => $price) {
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $price === null ? null : Money::ofMinor($price, 'NGN'),
            'currency' => $price === null ? null : 'NGN',
            'position' => $position,
            'attribute_signature' => "variant-{$position}",
        ]);
    }

    return $product;
}

/*
|--------------------------------------------------------------------------
| For the storefront's shopping aids (several products, links, similar
| products, best sellers, the wishlist)
|--------------------------------------------------------------------------
*/

/**
 * Gives a variant a distinctive number of units on hand, for proving a response never tells shoppers how many are left.
 */
function stockOnHandOf(ProductVariant $variant, int $onHand = 4321): int
{
    app(ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), $onHand);

    return $onHand;
}

/**
 * Makes a variant sold out: its stock is counted, none is left (a recount to zero), and it takes no backorders.
 */
function soldOut(ProductVariant $variant): void
{
    app(ChangeInventorySettings::class)->handle($variant, new InventorySettingsData(tracksStock: true, allowsBackorder: false));
    app(AdjustStock::class)->handle($variant, StockLocation::query()->sole(), change: null, count: 0, reason: AdjustmentReason::Recount);
}

/**
 * Section 14: a shopper's response names no stock field and never contains the number of units on hand.
 */
function expectNoStockQuantity(TestResponse $response, int $onHand = 4321): void
{
    $keys = [];
    collectJsonKeys($response->json(), $keys);

    expect(preg_grep('/quantity|on_hand|stock|reserved|units/i', $keys))->toBe([], 'A stock field is shown to shoppers')
        ->and(preg_match('/(?<![0-9])'.$onHand.'(?![0-9])/', (string) $response->getContent()))->toBe(0, 'The units on hand are shown to shoppers');
}

/**
 * Every key anywhere in a decoded JSON document.
 *
 * @param  array<array-key, mixed>|null  $document
 * @param  list<string>  $keys
 */
function collectJsonKeys(?array $document, array &$keys): void
{
    foreach ($document ?? [] as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            collectJsonKeys($value, $keys);
        }
    }
}

/**
 * Products for sale with stock, by slug, in the current store.
 *
 * @param  list<string>  $slugs
 * @return array<string, Product>
 */
function bestSellerProducts(array $slugs): array
{
    $products = [];

    foreach ($slugs as $slug) {
        $products[$slug] = storefrontProduct(['en' => $slug], attributes: ['slug' => $slug]);
        stockOnHandOf($products[$slug]->variants()->sole());
    }

    return $products;
}

/**
 * Writes an order straight into the current store's database, paid in full by default, selling the units given of each product's first variant. Only for counting what sold; real orders are placed with PlaceOrder.
 *
 * @param  list<array{0: Product, 1: int}>  $lines  Each product and how many units.
 * @param  array<string, mixed>  $order  Columns to change on the order, such as payment_status, is_test, status or placed_at.
 */
function soldOrder(array $lines, array $order = []): void
{
    $now = now();
    $orderId = DB::table('orders')->insertGetId([
        'public_id' => strtolower((string) Str::ulid()), 'checkout_id' => strtolower((string) Str::ulid()), 'fulfilment_status' => 'unfulfilled',
        'contact_email' => 'ada@example.com', 'contact_name' => 'Ada Obi', 'locale' => 'en', 'currency' => 'NGN', 'base_currency' => 'NGN', 'exchange_rate' => '1',
        'rate_source' => 'base', 'tax_mode' => 'inclusive', 'tax_calculated_by' => 'rate_table', 'tax_country_code' => 'NG', 'subtotal_amount' => 0, 'discount_amount' => 0,
        'shipping_amount' => 0, 'shipping_discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'base_total_amount' => 0,
        'payment_status' => 'paid', 'paid_at' => $now, 'placed_at' => $now, 'payment_due_at' => $now->copy()->addHour(), 'created_at' => $now, 'updated_at' => $now,
        ...$order,
    ]);

    foreach ($lines as $position => [$product, $quantity]) {
        $variant = $product->variants()->firstOrFail();
        DB::table('order_lines')->insert([
            'public_id' => strtolower((string) Str::ulid()), 'order_id' => $orderId, 'position' => $position, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'product_name' => 'Sold', 'sku' => $variant->sku, 'requires_shipping' => true, 'tax_class_name' => 'Standard', 'tax_outcome' => 'no_rate', 'quantity' => $quantity,
            'currency' => 'NGN', 'unit_price_amount' => 0, 'price_is_fixed' => false, 'subtotal_amount' => 0, 'discount_amount' => 0, 'net_amount' => 0, 'tax_amount' => 0,
            'total_amount' => 0, 'created_at' => $now,
        ]);
    }
}

/**
 * A staff request under /api/v1/staff/catalog/products, as the given staff member (none: no token).
 *
 * @param  array<string, mixed>  $data
 */
function productLinksRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'shop-front'): TestResponse
{
    forgetSignIns();
    $request = $as === null ? test()->withoutToken() : test()->withToken(staffTokenFor($as, $subdomain));
    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/catalog/products/'.ltrim($path, '/')), $data);
    test()->flushHeaders();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For StorefrontImagesTest.php
|--------------------------------------------------------------------------
*/

function imageStoreLimits(?int $storageInMegabytes): void
{
    test()->featureSource->give(test()->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['products' => 100, 'storage_in_megabytes' => $storageInMegabytes]));
    app(Features::class)->forget(test()->store->getTenantKey());
}

/**
 * @param  array<string, mixed>  $data
 */
function imageRequest(string $method, string $path, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $url = storeUrl('image-store', '/api/v1/staff'.$path);
    // Uploads go as multipart form data; everything else as JSON.
    $response = $method === 'POST'
        ? test()->withToken($token)->post($url, $data, ['Accept' => 'application/json'])
        : test()->withToken($token)->json($method, $url, $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @return array{product: string, variant: string}
 */
function imageProduct(): array
{
    $response = imageRequest('POST', '/catalog/products', ['name' => ['en' => 'Linen shirt'], 'variant' => ['price' => 1999, 'weight_grams' => 250]])->assertCreated();

    return ['product' => $response->json('data.id'), 'variant' => $response->json('data.variants.0.id')];
}
