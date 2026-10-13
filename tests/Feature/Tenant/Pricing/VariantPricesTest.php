<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Actions\AddStoreCurrency;
use App\Tenant\Pricing\Actions\ChangeStoreCurrency;
use App\Tenant\Pricing\Actions\SetFixedPrice;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Pricing\VariantPricer;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
 * Section 3.3: a price in another currency is the merchant's fixed price for
 * it, used as it is, or the base price converted at the store's rate, per
 * unit, rounded half-up to the currency's ISO 4217 decimals. A converted
 * compare-at price that is no longer higher is dropped. A whole list is
 * priced in a fixed number of queries.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('price-store');
    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['presentment_currencies' => 3])));

    [$this->owner, $this->viewer, $this->product] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $role = Role::findOrCreate('Accountant', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('pricing.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        // Manual rates make every conversion exact: 1 NGN = 0.0005 USD = 0.1 JPY = 0.0002 KWD.
        foreach (['USD' => '0.0005', 'JPY' => '0.1', 'KWD' => '0.0002'] as $code => $rate) {
            app(ChangeStoreCurrency::class)->setManualRate(app(AddStoreCurrency::class)->handle($code, Decimal::of(0)), Decimal::of($rate));
        }

        return [$owner, $viewer, Product::factory()->create(['name' => ['en' => 'Linen shirt'], 'slug' => 'linen-shirt'])];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

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

it('converts the base price per unit, rounding half-up to each currency\'s decimals', function (): void {
    $this->store->run(static function (): void {
        // USD, 2 decimals: ₦10.00 → $0.005 → 1 cent; ₦9.99 → $0.004995 → 0 cents.
        expect(unitPricesIn([variantPricedAt(1000), variantPricedAt(999)], 'USD'))->toBe([1, 0])
            // JPY, no decimals: ₦15.00 → ¥1.5 → ¥2; ₦14.99 → ¥1.499 → ¥1.
            ->and(unitPricesIn([variantPricedAt(1500), variantPricedAt(1499)], 'JPY'))->toBe([2, 1])
            // KWD, 3 decimals: ₦2.50 → 0.0005 KD → 0.001; ₦12,345.67 → 2.469134 KD → 2.469.
            ->and(unitPricesIn([variantPricedAt(250), variantPricedAt(1234567)], 'KWD'))->toBe([1, 2469])
            // The base currency as it is.
            ->and(unitPricesIn([variantPricedAt(1234567)], 'NGN'))->toBe([1234567]);

        $price = app(VariantPricer::class)->priceIn([$variant = variantPricedAt(1234567)], 'KWD')[$variant->id];
        expect($price->unitPrice->currency())->toBe('KWD')
            ->and($price->isFixed)->toBeFalse()
            ->and($price->includesTax)->toBeTrue()
            ->and($price->exchangeRate->rate->toString())->toBe('0.000200000000');
    });
});

it('uses a fixed price as it is, and drops a converted compare-at price that is no longer higher', function (): void {
    $this->store->run(static function (): void {
        $fixed = variantPricedAt(100000, 150000);
        app(SetFixedPrice::class)->handle($fixed, StoreCurrency::query()->where('currency_code', 'USD')->sole(), Money::ofMinor(4999, 'USD'), Money::ofMinor(5999, 'USD'));
        // ₦10.00 and ₦10.01 both become 1 cent.
        $squeezed = variantPricedAt(1000, 1001);
        $unpriced = variantPricedAt(null);

        $prices = app(VariantPricer::class)->priceIn([$fixed, $squeezed, $unpriced], 'USD');

        expect($prices)->toHaveKeys([$fixed->id, $squeezed->id])->not->toHaveKey($unpriced->id)
            ->and([$prices[$fixed->id]->unitPrice->minorAmount(), $prices[$fixed->id]->compareAtPrice?->minorAmount(), $prices[$fixed->id]->isFixed])->toBe([4999, 5999, true])
            ->and($prices[$squeezed->id]->compareAtPrice)->toBeNull();

        // Other currencies still convert: ₦1,000.00 → ¥100.
        expect(unitPricesIn([$fixed], 'JPY'))->toBe([100]);
    });
});

it('refuses currencies the store doesn\'t offer now', function (): void {
    $this->store->run(static function (): void {
        $variant = variantPricedAt(1000);
        app(ChangeStoreCurrency::class)->handle(StoreCurrency::query()->where('currency_code', 'JPY')->sole(), false, null);

        foreach (['JPY', 'EUR', 'GBP'] as $currency) {
            expect(static fn () => app(VariantPricer::class)->priceIn([$variant], $currency))->toThrow(CurrencyNotOfferedException::class);
        }
    });
});

it('prices a whole list in a fixed number of queries', function (): void {
    $this->store->run(static function (): void {
        $one = [variantPricedAt(1000)];
        $many = array_map(static fn (int $kobo): ProductVariant => variantPricedAt($kobo), range(1001, 1040));
        app(SetFixedPrice::class)->handle($many[3], StoreCurrency::query()->where('currency_code', 'USD')->sole(), Money::ofMinor(99, 'USD'), null);

        $queries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries++;
        });

        app(VariantPricer::class)->priceIn($one, 'USD');
        $forOne = $queries;
        $queries = 0;
        app(VariantPricer::class)->priceIn($many, 'USD');
        $forMany = $queries;

        expect($forOne)->toBeLessThanOrEqual(5)
            ->and($forMany)->toBe($forOne);
    });
});

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

it('lets staff set and remove fixed prices, and shows what a unit costs in each currency', function (): void {
    $variant = $this->store->run(static fn (): ProductVariant => variantPricedAt(200000));

    $pricing = variantPriceRequest('PUT', "/{$variant->public_id}/prices/USD", ['price' => 1999, 'compare_at_price' => 2499])->assertOk();
    expect($pricing->json('data.fixed_prices.0'))->toMatchArray(['currency' => 'USD'])
        ->and($pricing->json('data.fixed_prices.0.price.amount'))->toBe(1999)
        ->and(collect($pricing->json('data.prices'))->map(static fn (array $price): array => [$price['price']['currency'], $price['price']['amount'], $price['is_fixed']])->all())
        ->toBe([['NGN', 200000, false], ['JPY', 200, false], ['KWD', 400, false], ['USD', 1999, true]]);

    variantPriceRequest('PUT', "/{$variant->public_id}/prices/USD", ['price' => 1999, 'compare_at_price' => 1999])->assertUnprocessable()->assertJsonValidationErrors('compare_at_price');
    variantPriceRequest('PUT', "/{$variant->public_id}/prices/USD", ['price' => 19.99])->assertJsonValidationErrors('price');
    variantPriceRequest('PUT', "/{$variant->public_id}/prices/USD", ['price' => -1])->assertJsonValidationErrors('price');
    variantPriceRequest('PUT', "/{$variant->public_id}/prices/NGN", ['price' => 1999])->assertNotFound();
    variantPriceRequest('PUT', "/{$variant->public_id}/prices/EUR", ['price' => 1999])->assertNotFound();
    variantPriceRequest('PUT', "/{$variant->public_id}/prices/USD", ['price' => 2999], as: $this->viewer)->assertForbidden();
    variantPriceRequest('DELETE', "/{$variant->public_id}/prices/USD", as: $this->viewer)->assertForbidden();
    variantPriceRequest('GET', "/{$variant->public_id}", as: $this->viewer)->assertOk()->assertJsonCount(1, 'data.fixed_prices');

    variantPriceRequest('DELETE', "/{$variant->public_id}/prices/USD")->assertOk()->assertJsonCount(0, 'data.fixed_prices')->assertJsonPath('data.prices.3.price.amount', 100);

    $this->store->run(static function (): void {
        expect(Audit::query()->where('auditable_type', 'fixed_price')->pluck('event')->all())->toBe(['created', 'deleted']);
    });
});

it('locks the base currency once a fixed price exists', function (): void {
    $this->store->run(static function (): void {
        // The manual rates set up for these tests lock it already; clear them to see the fixed price alone.
        StoreCurrency::query()->each(static fn (StoreCurrency $currency): StoreCurrency => app(ChangeStoreCurrency::class)->setManualRate($currency, null));
        expect(app(PricedRecordsRegistry::class)->anyExist())->toBeFalse();

        app(SetFixedPrice::class)->handle(variantPricedAt(null), StoreCurrency::query()->where('currency_code', 'USD')->sole(), Money::ofMinor(500, 'USD'), null);
        expect(app(PricedRecordsRegistry::class)->anyExist())->toBeTrue();
    });
});

it('keeps fixed prices inside their own store', function (): void {
    $variant = $this->store->run(static fn (): ProductVariant => variantPricedAt(200000));
    variantPriceRequest('PUT', "/{$variant->public_id}/prices/USD", ['price' => 1999])->assertOk();

    $other = createStore('other-price-store');
    $other->run(static function () use ($variant): void {
        expect(App\Tenant\Pricing\Models\FixedPrice::query()->count())->toBe(0)
            ->and(ProductVariant::query()->where('public_id', $variant->public_id)->exists())->toBeFalse();
    });
});
