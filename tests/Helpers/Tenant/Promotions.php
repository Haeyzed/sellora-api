<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Actions\PriceWithDiscounts;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For PromotionsTest.php and the other promotion tests
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to the "promotions-store" store's staff promotions API, under /api/v1/staff/promotions, as a staff member (the test's owner when left out) or with a token.
 *
 * @param  array<string, mixed>  $data
 */
function promotionRequest(string $method, string $path, array $data = [], StaffMember|string|null $as = null): TestResponse
{
    forgetSignIns();
    $as ??= test()->owner;
    $token = $as instanceof StaffMember ? staffTokenFor($as, 'promotions-store') : $as;

    $response = test()->withToken($token)->json($method, storeUrl('promotions-store', '/api/v1/staff/promotions'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * The fields of a running automatic promotion taking 10% off the order, with any overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function percentOffOrder(array $overrides = []): array
{
    return [...['name' => 'Ten off', 'title' => ['en' => '10% off'], 'method' => 'automatic', 'type' => 'percentage', 'applies_to' => 'order', 'percent' => '10'], ...$overrides];
}

/**
 * Lets the current store sell in another currency, at a manual rate (units of it per unit of the base currency).
 */
function offerStoreCurrency(string $currency, string $rate = '1'): void
{
    DB::table('store_currencies')->insert(['currency_code' => $currency, 'is_active' => true, 'margin_percent' => 0, 'manual_rate' => $rate, 'manual_rate_set_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
}

/*
|--------------------------------------------------------------------------
| For DiscountsTest.php
|--------------------------------------------------------------------------
*/

/**
 * A product with one variant priced in the store's base currency, optionally on sale (a compare-at price) and in categories.
 *
 * @param  list<Category>  $categories
 */
function promotedVariant(int $price, ?int $compareAt = null, array $categories = []): ProductVariant
{
    $currency = app(FindStoreSettings::class)->handle()->currency_code;
    $product = Product::factory()->create();
    $product->categories()->attach(array_map(static fn (Category $category): int => $category->id, $categories));

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => Money::ofMinor($price, $currency),
        'compare_at_price' => $compareAt === null ? null : Money::ofMinor($compareAt, $currency),
        'currency' => $currency,
    ]);
}

/**
 * A running promotion with the attributes given; amounts as minor units in the base currency.
 *
 * @param  array<string, mixed>  $attributes
 */
function runningPromotion(array $attributes = []): Promotion
{
    $currency = app(FindStoreSettings::class)->handle()->currency_code;

    foreach (['discount', 'max_discount', 'min_subtotal'] as $key) {
        if (is_int($attributes[$key] ?? null)) {
            $attributes[$key] = Money::ofMinor($attributes[$key], $currency);
        }
    }

    return Promotion::factory()->create($attributes);
}

/**
 * The lines priced with their discounts, keyed "line-1", "line-2" and so on, in the store (or another offered) currency.
 *
 * @param  list<array{0: ProductVariant, 1: int}>  $lines  Each variant and how many.
 */
function discountedBasket(array $lines, ?string $code = null, ?DiscountBuyer $buyer = null, ?int $shipping = null, ?string $currency = null, ?CarbonImmutable $at = null, ?TaxAddress $destination = null): DiscountedBasket
{
    $currency ??= app(FindStoreSettings::class)->handle()->currency_code;
    $pricingLines = [];

    foreach (array_values($lines) as $position => [$variant, $quantity]) {
        $pricingLines[] = ['key' => 'line-'.($position + 1), 'variant' => $variant, 'quantity' => $quantity];
    }

    return app(PriceWithDiscounts::class)->handle($currency, $pricingLines, $destination, $shipping === null ? null : Money::ofMinor($shipping, $currency), $buyer ?? DiscountBuyer::guest(null), $code, $at);
}

/**
 * What each line gets off, in minor units, by key.
 *
 * @return array<string, int>
 */
function lineDiscountsOf(DiscountedBasket $basket): array
{
    return array_map(static fn (Money $amount): int => $amount->minorAmount(), $basket->discounts->lineDiscounts);
}
