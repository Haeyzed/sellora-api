<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Actions\AddStoreCurrency;
use App\Tenant\Pricing\Actions\ChangeStoreCurrency;
use App\Tenant\Pricing\Actions\CreateTaxRate;
use App\Tenant\Pricing\Actions\SetFixedPrice;
use App\Tenant\Pricing\Actions\UpdateTaxClass;
use App\Tenant\Pricing\Data\PricingRequest;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Pricing\Data\TaxClassChanges;
use App\Tenant\Pricing\Data\TaxCustomer;
use App\Tenant\Pricing\Enums\TaxOutcome;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Pricing\PriceCalculator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 3.3: Cart, Checkout and Orders price lines with one calculation:
 * unit prices in the quote's currency (fixed, or converted per unit), the
 * line's taxable amount (subtotal − discount) taxed per line, shipping as
 * one more taxable line, and totals that are exact sums. Customers get prices
 * in the currency they ask for while the store offers it, and in the store's
 * own currency otherwise.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('quote-store');
    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['presentment_currencies' => 3])));

    [$this->owner, $this->viewer, $this->shirt] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        $role = Role::findOrCreate('Accountant', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('pricing.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        // Exact manual rates: 1 NGN = 0.0005 USD = 0.1 JPY = 0.0002 KWD.
        foreach (['USD' => '0.0005', 'JPY' => '0.1', 'KWD' => '0.0002'] as $code => $rate) {
            app(ChangeStoreCurrency::class)->setManualRate(app(AddStoreCurrency::class)->handle($code, Decimal::of(0)), Decimal::of($rate));
        }

        $standard = TaxClass::query()->where('name', 'Standard')->sole();
        foreach ([['NG', null, '7.5'], ['US', 'CA', '7.25'], ['JP', null, '10'], ['KW', null, '5']] as [$country, $state, $rate]) {
            app(CreateTaxRate::class)->handle($standard, $country, $state, 'Tax', Decimal::of($rate));
        }

        $product = Product::factory()->create(['name' => ['en' => 'Linen shirt'], 'slug' => 'linen-shirt']);
        $product->forceFill(['status' => ProductStatus::Active])->save();

        // ₦10,750.00, tax included.
        return [$owner, $viewer, ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SHIRT', 'price' => Money::ofMinor(1075000, 'NGN'), 'compare_at_price' => Money::ofMinor(1290000, 'NGN'), 'currency' => 'NGN'])];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('takes tax out of the price in a tax-inclusive store, per line, after the discount', function (): void {
    $this->store->run(static function (): void {
        // 2 × ₦10,750 = ₦21,500, of which 7.5/107.5 is tax: ₦1,500.
        expect(basketTotals(priceQuoteFor('NGN', 2)))->toBe([2150000, 0, 150000, 2150000]);

        // ₦1,500 off: ₦20,000 × 7.5/107.5 = ₦1,395.348… → ₦1,395.35.
        $discounted = priceQuoteFor('NGN', 2, discount: Money::ofMinor(150000, 'NGN'));
        expect(basketTotals($discounted))->toBe([2150000, 150000, 139535, 2000000])
            ->and($discounted->lines['shirt']->tax->net->minorAmount())->toBe(1860465)
            ->and($discounted->taxMode)->toBe(TaxMode::Inclusive)
            ->and($discounted->taxCalculatedBy)->toBe('rate_table');
    });
});

it('adds tax on top in a tax-exclusive store', function (): void {
    $this->store->run(static function (): void {
        DB::table('store_settings')->update(['tax_mode' => TaxMode::Exclusive->value]);

        expect(basketTotals(priceQuoteFor('NGN')))->toBe([1075000, 0, 80625, 1155625]);
    });
});

it('prices and taxes in the customer\'s currency, rounding each to its own decimals', function (): void {
    $this->store->run(static function (): void {
        // $5.375 → $5.38 a unit; California 7.25% inside it: 538 × 7.25 / 107.25 = 36.37 → 36 cents.
        $dollars = priceQuoteFor('USD', destination: new TaxAddress('US', 'CA'));
        expect(basketTotals($dollars))->toBe([538, 0, 36, 538])
            ->and($dollars->exchangeRate->rate->toString())->toBe('0.000500000000')
            ->and($dollars->lines['shirt']->price->compareAtPrice?->minorAmount())->toBe(645);

        // ¥1,075 with 10% inside: 97.7 → ¥98. Three units are priced per unit, then multiplied.
        expect(basketTotals(priceQuoteFor('JPY', 3, new TaxAddress('JP'))))->toBe([3225, 0, 293, 3225])
            // 2.150 KD with 5% inside: 102.38 → 0.102 KD.
            ->and(basketTotals(priceQuoteFor('KWD', destination: new TaxAddress('KW'))))->toBe([2150, 0, 102, 2150]);
    });
});

it('uses a fixed price as it is in the quote', function (): void {
    $this->store->run(function (): void {
        app(SetFixedPrice::class)->handle($this->shirt, StoreCurrency::query()->where('currency_code', 'USD')->sole(), Money::ofMinor(499, 'USD'), null);

        $basket = priceQuoteFor('USD', 2, new TaxAddress('US', 'CA'));
        expect($basket->lines['shirt']->price->isFixed)->toBeTrue()
            ->and(basketTotals($basket))->toBe([998, 0, 67, 998]);
    });
});

it('taxes shipping at the store\'s shipping class, or not at all without one', function (): void {
    $this->store->run(static function (): void {
        $basket = priceQuoteFor('NGN', shipping: Money::ofMinor(107500, 'NGN'));
        expect($basket->shipping?->tax->minorAmount())->toBe(7500)
            ->and(basketTotals($basket))->toBe([1075000, 0, 82500, 1182500]);

        app(UpdateTaxClass::class)->handle(TaxClass::query()->where('name', 'Standard')->sole(), new TaxClassChanges(usedForShipping: false));
        $untaxed = priceQuoteFor('NGN', shipping: Money::ofMinor(107500, 'NGN'));
        expect($untaxed->shipping?->outcome)->toBe(TaxOutcome::Exempt)
            ->and($untaxed->shipping?->tax->isZero())->toBeTrue()
            ->and($untaxed->total()->minorAmount())->toBe(1182500);
    });
});

it('charges a tax-exempt customer no tax, on goods or shipping', function (): void {
    $this->store->run(static function (): void {
        $basket = priceQuoteFor('NGN', shipping: Money::ofMinor(107500, 'NGN'), customer: new TaxCustomer(isTaxExempt: true, taxId: 'NG12345678', taxIdCountry: 'NG'));

        expect($basket->tax()->isZero())->toBeTrue()
            ->and($basket->lines['shirt']->tax->outcome)->toBe(TaxOutcome::Exempt)
            ->and($basket->shipping?->outcome)->toBe(TaxOutcome::Exempt);
    });
});

it('lets staff preview a quote, with lines that have no rate at the destination marked', function (): void {
    $shirt = $this->shirt->public_id;

    quoteRequest(['currency' => 'USD', 'country_code' => 'US', 'state_code' => 'ca', 'lines' => [['variant' => $shirt, 'quantity' => 2, 'discount' => 76]], 'shipping' => 1000], as: $this->viewer)
        ->assertOk()
        ->assertJsonPath('data.destination', ['country_code' => 'US', 'state_code' => 'CA'])
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.exchange_rate.source', 'manual')
        ->assertJsonPath('data.lines.0.unit_price.amount', 538)
        ->assertJsonPath('data.lines.0.discount.amount', 76)
        ->assertJsonPath('data.lines.0.tax_outcome', 'taxed')
        ->assertJsonPath('data.lines.0.taxes.0.rate', '7.2500')
        ->assertJsonPath('data.shipping.total.amount', 1000)
        ->assertJsonPath('data.totals.total.amount', 2000)
        ->assertJsonPath('data.has_missing_rates', false);

    $abroad = quoteRequest(['country_code' => 'DE', 'lines' => [['variant' => $shirt, 'quantity' => 1]]])->assertOk();
    expect($abroad->json('data.currency'))->toBe('NGN')
        ->and($abroad->json('data.lines.0.tax_outcome'))->toBe('no_rate')
        ->and($abroad->json('data.has_missing_rates'))->toBeTrue()
        ->and($abroad->json('data.totals.tax.amount'))->toBe(0);
});

it('refuses currencies not offered, unpriced or trashed variants, and discounts bigger than the line', function (): void {
    $unpriced = $this->store->run(fn (): ProductVariant => ProductVariant::factory()->create(['product_id' => $this->shirt->product_id, 'sku' => 'NOPRICE', 'attribute_signature' => 'x', 'price' => null, 'compare_at_price' => null, 'currency' => null]));
    $shirt = $this->shirt->public_id;

    quoteRequest(['currency' => 'EUR', 'lines' => [['variant' => $shirt, 'quantity' => 1]]])->assertUnprocessable()->assertJsonPath('code', 'currency_not_offered');
    quoteRequest(['lines' => [['variant' => $unpriced->public_id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonPath('code', 'variant_not_priced');
    quoteRequest(['lines' => [['variant' => $shirt, 'quantity' => 1, 'discount' => 1075001]]])->assertUnprocessable()->assertJsonPath('code', 'discount_exceeds_line');
    quoteRequest(['lines' => [['variant' => $shirt, 'quantity' => 0]]])->assertJsonValidationErrors('lines.0.quantity');
    quoteRequest(['lines' => []])->assertJsonValidationErrors('lines');

    $this->store->run(fn () => ProductVariant::query()->whereKey($this->shirt->id)->delete());
    quoteRequest(['lines' => [['variant' => $shirt, 'quantity' => 1]]])->assertJsonValidationErrors('lines.0.variant');

    $stranger = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());
    quoteRequest(['lines' => [['variant' => $unpriced->public_id, 'quantity' => 1]]], as: $stranger)->assertForbidden();
});

it('shows customers prices in the currency they ask for while it is offered, and in the store\'s own otherwise', function (): void {
    pricedStorefrontProduct('?currency=USD')->assertOk()->assertJsonPath('data.variants.0.price.amount', 538)->assertJsonPath('data.variants.0.price.currency', 'USD')
        ->assertJsonPath('data.variants.0.compare_at_price.amount', 645);
    pricedStorefrontProduct('?currency=KWD')->assertJsonPath('data.variants.0.price.amount', 2150)->assertJsonPath('data.variants.0.price.currency', 'KWD');

    $list = $this->getJson(storeUrl('quote-store', '/api/v1/catalog/products?currency=JPY'))->assertOk();
    tenancy()->end();
    expect($list->json('data.0.variants.0.price'))->toMatchArray(['amount' => 1075, 'currency' => 'JPY']);

    // Not one of the store's currencies, inactive, or without a usable rate: the store's own.
    $this->store->run(static function (): void {
        app(ChangeStoreCurrency::class)->handle(StoreCurrency::query()->where('currency_code', 'JPY')->sole(), false, null);
        app(ChangeStoreCurrency::class)->setManualRate(StoreCurrency::query()->where('currency_code', 'KWD')->sole(), null);
    });

    foreach (['', '?currency=EUR', '?currency=JPY', '?currency=KWD', '?currency=NGN'] as $query) {
        pricedStorefrontProduct($query)->assertOk()->assertJsonPath('data.variants.0.price.currency', 'NGN')->assertJsonPath('data.variants.0.price.amount', 1075000);
    }

    pricedStorefrontProduct('?currency=usd')->assertUnprocessable()->assertJsonValidationErrors('currency');
});

it('keeps quotes and prices inside their own store', function (): void {
    $other = createStore('other-quote-store');
    $otherOwner = $other->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $token = $other->run(static fn (): string => app(AccessTokenIssuer::class)->issue($otherOwner, StaffMember::GUARD, 'test')->plainTextToken);
    forgetSignIns();

    $this->withToken($token)->postJson(storeUrl('other-quote-store', '/api/v1/staff/pricing/quotes'), ['currency' => 'USD', 'lines' => [['variant' => $this->shirt->public_id, 'quantity' => 1]]])
        ->assertJsonValidationErrors('lines.0.variant');
    tenancy()->end();

    $other->run(static function (): void {
        expect(static fn () => app(PriceCalculator::class)->quote(new PricingRequest('USD', [])))->toThrow(App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException::class);
    });
});
