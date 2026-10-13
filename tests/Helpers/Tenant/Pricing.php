<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Actions\CalculateTax;
use App\Tenant\Pricing\Actions\CreateTaxRate;
use App\Tenant\Pricing\Data\PricedBasket;
use App\Tenant\Pricing\Data\PricingLine;
use App\Tenant\Pricing\Data\PricingRequest;
use App\Tenant\Pricing\Data\TaxableLine;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Pricing\Data\TaxClassData;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Enums\TaxLineKind;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Pricing\PriceCalculator;
use App\Tenant\Pricing\VariantPricer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| For PriceQuoteTest.php
|--------------------------------------------------------------------------
*/

function priceQuoteFor(string $currency, int $quantity = 1, ?TaxAddress $destination = null, ?Money $discount = null, ?Money $shipping = null): PricedBasket
{
    return app(PriceCalculator::class)->quote(new PricingRequest($currency, [new PricingLine('shirt', ProductVariant::query()->where('sku', 'SHIRT')->sole(), $quantity, $discount)], $destination, $shipping));
}

/**
 * @return array{0: int, 1: int, 2: int, 3: int} Subtotal, discount, tax and total in minor units.
 */
function basketTotals(PricedBasket $basket): array
{
    return [$basket->subtotal()->minorAmount(), $basket->discount()->minorAmount(), $basket->tax()->minorAmount(), $basket->total()->minorAmount()];
}

/**
 * @param  array<string, mixed>  $data
 */
function quoteRequest(array $data, ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->postJson(storeUrl('quote-store', '/api/v1/staff/pricing/quotes'), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function pricedStorefrontProduct(string $query = ''): TestResponse
{
    $response = test()->getJson(storeUrl('quote-store', '/api/v1/catalog/products/linen-shirt'.$query));
    tenancy()->end();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For StoreCurrenciesTest.php
|--------------------------------------------------------------------------
*/

/**
 * Market rates, as the hourly fetch records them: units per US dollar.
 *
 * @param  array<string, string>  $unitsPerUsd
 */
function marketRates(array $unitsPerUsd, int $hoursAgo = 0): void
{
    foreach (['USD' => '1', ...$unitsPerUsd] as $currency => $rate) {
        DB::connection('central')->table('exchange_rates')->insert(['currency' => $currency, 'units_per_usd' => $rate, 'source' => 'fake', 'fetched_at' => now()->subHours($hoursAgo), 'created_at' => now()]);
    }
}

/**
 * @param  array<string, mixed>  $data
 */
function currencyRequest(string $method, string $path, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('currency-store', '/api/v1/staff/pricing/currencies'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function storefrontCurrencies(string $query = ''): TestResponse
{
    $response = test()->getJson(storeUrl('currency-store', '/api/v1/catalog/currencies'.$query));
    tenancy()->end();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For TaxCalculationTest.php
|--------------------------------------------------------------------------
*/

/**
 * The store's class with this name, as a calculator sees it.
 */
function taxClassNamed(string $name): TaxClassData
{
    return TaxClassData::of(TaxClass::query()->where('name', $name)->sole());
}

function addTaxRate(string $className, string $countryCode, ?string $stateCode, string $rate, string $name = 'VAT'): void
{
    app(CreateTaxRate::class)->handle(TaxClass::query()->where('name', $className)->sole(), $countryCode, $stateCode, $name, Decimal::of($rate));
}

/**
 * @param  array<string, array{0: int, 1: string}>  $lines  Minor amount and class name, by line key.
 */
function calculatedTaxFor(array $lines, TaxAddress $destination, TaxMode $taxMode = TaxMode::Exclusive, string $currency = 'NGN'): App\Tenant\Pricing\Data\TaxResult
{
    $taxableLines = [];

    foreach ($lines as $key => [$amount, $className]) {
        $taxableLines[] = new TaxableLine((string) $key, TaxLineKind::Goods, Money::ofMinor($amount, $currency), taxClassNamed($className));
    }

    return app(CalculateTax::class)->handle(new TaxRequest($currency, $taxMode, new TaxAddress('NG', 'LA'), $destination, $taxableLines));
}

/*
|--------------------------------------------------------------------------
| For TaxSetupTest.php
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $data
 */
function pricingRequest(string $method, string $path, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('tax-setup-store', '/api/v1/staff/pricing'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function taxClassId(string $name): string
{
    return test()->store->run(static fn (): string => TaxClass::query()->where('name', $name)->sole()->public_id);
}

/*
|--------------------------------------------------------------------------
| For VariantPricesTest.php
|--------------------------------------------------------------------------
*/

/**
 * A variant of the test product, priced in naira minor units.
 */
function variantPricedAt(?int $kobo, ?int $compareAtKobo = null, ?string $sku = null): ProductVariant
{
    $sku ??= 'SKU-'.Str::random(8);

    return ProductVariant::factory()->create([
        'product_id' => test()->product->id, 'sku' => $sku, 'attribute_signature' => $sku,
        'price' => $kobo === null ? null : Money::ofMinor($kobo, 'NGN'),
        'compare_at_price' => $compareAtKobo === null ? null : Money::ofMinor($compareAtKobo, 'NGN'),
        'currency' => $kobo === null ? null : 'NGN',
    ]);
}

/**
 * @param  list<ProductVariant>  $variants
 * @return list<int|null> Each variant's unit price in minor units, null when left out.
 */
function unitPricesIn(array $variants, string $currency): array
{
    $prices = app(VariantPricer::class)->priceIn($variants, $currency);

    return array_map(static fn (ProductVariant $variant): ?int => ($prices[$variant->id] ?? null)?->unitPrice->minorAmount(), $variants);
}

/**
 * @param  array<string, mixed>  $data
 */
function variantPriceRequest(string $method, string $path, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('price-store', '/api/v1/staff/pricing/variants'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}
