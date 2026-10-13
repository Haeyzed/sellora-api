<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For StockLevelsTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $data
 */
function inventoryRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'stock-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'stock-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/inventory'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For StockReservationsTest.php
|--------------------------------------------------------------------------
*/

function newStockHolder(string $type = 'checkout'): StockHolder
{
    return new StockHolder($type, Str::lower((string) Str::ulid()));
}

function variantWithStockOnHand(int $onHand, string $sku = 'LIN-1'): ProductVariant
{
    $variant = ProductVariant::factory()->create(['sku' => $sku]);

    if ($onHand > 0) {
        app(ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), $onHand);
    }

    return $variant;
}

function stockItemOf(ProductVariant $variant): StockItem
{
    return StockItem::query()->where('product_variant_id', $variant->id)->sole();
}

/*
|--------------------------------------------------------------------------
| For StockSignalsTest.php
|--------------------------------------------------------------------------
*/

/**
 * A published product with priced variants, by SKU.
 *
 * @param  list<string>  $skus
 * @return array<string, ProductVariant>
 */
function variantsForSale(array $skus, string $slug = 'linen-shirt'): array
{
    $product = Product::factory()->create(['name' => ['en' => 'Linen shirt'], 'slug' => $slug]);
    $product->forceFill(['status' => ProductStatus::Active])->save();
    $variants = [];

    foreach ($skus as $position => $sku) {
        $variants[$sku] = ProductVariant::factory()->create([
            'product_id' => $product->id, 'sku' => $sku, 'price' => Money::ofMinor(1999, 'NGN'), 'currency' => 'NGN',
            'position' => $position, 'attribute_signature' => $sku,
        ]);
    }

    return $variants;
}

/*
|--------------------------------------------------------------------------
| For UnitCostTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $data
 */
function costRequest(string $method, string $path, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('cost-store', '/api/v1/staff/inventory'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}
