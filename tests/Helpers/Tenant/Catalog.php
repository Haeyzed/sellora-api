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
