<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Pricing\Models\FixedPrice;
use Illuminate\Support\Facades\DB;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 12: a cart is priced every time it is read, through the same
 * calculation checkout charges (promotions included), in the currency the
 * shopper chose while the store offers it. Lines that can no longer be
 * bought stay, marked, outside the totals. A line says when the merchant
 * changed its price since the shopper saw it; exchange rates moving never
 * count. Tax is an estimate, to the country the shopper gave or the store's
 * own. Reading takes the same number of queries for 1 line as for 100.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('prices the cart with its promotions, and marks tax as an estimate', function (): void {
    $token = newGuestCart();
    $variants = $this->store->run(static function (): array {
        runningPromotion(['percent' => Decimal::of(10)]);

        return [cartVariant(1999)->public_id, cartVariant(3001)->public_id];
    });

    cartRequest('POST', '/lines', ['variant' => $variants[0]], token: $token)->assertOk();
    $cart = cartRequest('POST', '/lines', ['variant' => $variants[1], 'quantity' => 2], token: $token)->assertOk();

    // 10% of 8001 is 800, spread 200 and 600; the store's prices include tax, and it has no rates.
    expect($cart->json('data.lines.*.discount.amount'))->toBe([200, 600])
        ->and($cart->json('data.totals.*.amount'))->toBe([8001, 800, 0, 0, 7201])
        ->and($cart->json('data.discounts.0.class'))->toBe('order')
        ->and($cart->json('data.tax'))->toMatchArray(['is_estimate' => true, 'mode' => 'inclusive', 'country_code' => 'NG', 'chosen_country_code' => null])
        // These lines ship, and the store ships nowhere yet: checkout waits for a shipping method.
        ->and($cart->json('data.can_check_out'))->toBeFalse()
        ->and($cart->json('data.shipping'))->toMatchArray(['required' => true, 'reason' => 'no_zone', 'method' => null, 'amount' => null]);
});

it('keeps lines that can no longer be bought, marked and outside the totals, and says what is on backorder', function (): void {
    $token = newGuestCart();
    $variants = $this->store->run(static fn (): array => [cartVariant(1000), cartVariant(2000), cartVariant(3000), cartVariant(4000), cartVariant(5000)]);

    foreach ($variants as $variant) {
        cartRequest('POST', '/lines', ['variant' => $variant->public_id], token: $token)->assertOk();
    }

    $this->store->run(static function () use ($variants): void {
        // Nothing here ships, so only the lines decide whether checkout can start.
        foreach ($variants as $variant) {
            $variant->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();
        }

        $variants[0]->product->forceFill(['status' => ProductStatus::Draft])->save();
        $variants[1]->forceFill(['price' => null, 'compare_at_price' => null, 'currency' => null])->save();
        $variants[2]->delete();
        app(ChangeInventorySettings::class)->handle($variants[3], new InventorySettingsData(tracksStock: true));
        app(ChangeInventorySettings::class)->handle($variants[4], new InventorySettingsData(tracksStock: true, allowsBackorder: true));
    });

    $cart = cartRequest('GET', token: $token)->assertOk();

    expect($cart->json('data.lines.*.status'))->toBe(['unavailable', 'unavailable', 'unavailable', 'out_of_stock', 'available'])
        ->and($cart->json('data.lines.*.backorder'))->toBe([false, false, false, false, true])
        ->and($cart->json('data.lines.*.unit_price.amount'))->toBe([null, null, null, null, 5000])
        ->and($cart->json('data.totals.total.amount'))->toBe(5000)
        ->and($cart->json('data.can_check_out'))->toBeFalse()
        ->and($cart->json('data.shipping.required'))->toBeFalse()
        ->and($cart->getContent())->not->toContain('on_hand')->not->toContain('"available_quantity"');
});

it('prices in a currency the shopper chose while the store offers it, and in the store\'s own while its rate is out of date', function (): void {
    marketRates(['NGN' => '1500']);
    $token = newGuestCart();
    $variant = $this->store->run(static function (): string {
        DB::table('store_currencies')->insert(['currency_code' => 'USD', 'is_active' => true, 'margin_percent' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return cartVariant(150000)->public_id;
    });
    cartRequest('POST', '/lines', ['variant' => $variant], token: $token)->assertOk();

    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token)->assertStatus(428);
    cartRequest('PATCH', '', ['currency' => 'EUR'], token: $token, ifMatch: '"2"')->assertUnprocessable()->assertJsonPath('code', 'currency_not_offered');
    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: '"2"')->assertOk()
        ->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.chosen_currency_unavailable', null)->assertJsonPath('data.totals.total', ['amount' => 100, 'currency' => 'USD', 'formatted' => '$1.00']);

    // 49 hours without a fresh market rate: the cart falls back to naira, and keeps the shopper's choice.
    $this->travel(49)->hours();
    cartRequest('GET', token: $token)->assertOk()->assertJsonPath('data.currency', 'NGN')->assertJsonPath('data.chosen_currency_unavailable', 'USD')->assertJsonPath('data.totals.total.amount', 150000);

    marketRates(['NGN' => '1500']);
    cartRequest('GET', token: $token)->assertOk()->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.chosen_currency_unavailable', null);

    cartRequest('PATCH', '', ['currency' => null], token: $token, ifMatch: '"3"')->assertOk()->assertJsonPath('data.currency', 'NGN');
});

it('says when the merchant changed a price since the shopper saw it, but not when only the exchange rate moved', function (): void {
    $token = newGuestCart();
    [$shirt, $hat] = $this->store->run(static function (): array {
        offerStoreCurrency('USD', '0.001');

        return [cartVariant(150000), cartVariant(50000)];
    });

    cartRequest('POST', '/lines', ['variant' => $shirt->public_id], token: $token)->assertOk();
    cartRequest('POST', '/lines', ['variant' => $hat->public_id], token: $token)->assertOk();
    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: '"3"')->assertOk();

    // The rate moves: prices in dollars change, but nobody changed a price.
    $this->store->run(static fn () => DB::table('store_currencies')->update(['manual_rate' => '0.0012']));
    cartRequest('GET', token: $token)->assertOk()->assertJsonPath('data.lines.0.unit_price.amount', 180)->assertJsonPath('data.lines.0.price_changed', null);

    // The merchant raises the shirt's naira price: flagged in dollars, at today's rate.
    $this->store->run(static fn () => $shirt->forceFill(['price' => Money::ofMinor(200000, 'NGN')])->save());
    $cart = cartRequest('GET', token: $token)->assertOk();
    expect($cart->json('data.lines.0.price_changed'))->toBe(['was' => ['amount' => 180, 'currency' => 'USD', 'formatted' => '$1.80'], 'now' => ['amount' => 240, 'currency' => 'USD', 'formatted' => '$2.40']])
        ->and($cart->json('data.lines.1.price_changed'))->toBeNull();

    // It stays until the line is changed or the changes are acknowledged.
    cartRequest('GET', token: $token)->assertJsonPath('data.lines.0.price_changed.now.amount', 240);
    cartRequest('POST', '/price-change-acknowledgements', token: $token)->assertStatus(428);
    cartRequest('POST', '/price-change-acknowledgements', token: $token, ifMatch: '"3"')->assertStatus(412);
    cartRequest('POST', '/price-change-acknowledgements', token: $token, ifMatch: '"4"')->assertOk()->assertJsonPath('data.lines.0.price_changed', null);

    $this->store->run(static fn () => $hat->forceFill(['price' => Money::ofMinor(40000, 'NGN')])->save());
    $line = cartRequest('GET', token: $token)->assertJsonPath('data.lines.1.price_changed.was.amount', 60)->json('data.lines.1.id');
    cartRequest('PATCH', "/lines/{$line}", ['quantity' => 2], token: $token, ifMatch: '"5"')->assertOk()->assertJsonPath('data.lines.1.price_changed', null);

    // Adding more of a line is looking at it again too.
    $this->store->run(static fn () => $shirt->forceFill(['price' => Money::ofMinor(210000, 'NGN')])->save());
    cartRequest('GET', token: $token)->assertJsonPath('data.lines.0.price_changed.now.amount', 252);
    cartRequest('POST', '/lines', ['variant' => $shirt->public_id], token: $token)->assertOk()->assertJsonPath('data.lines.0.price_changed', null);
});

it('compares a fixed price in its own currency when the merchant changes it', function (): void {
    $token = newGuestCart();
    $variant = $this->store->run(static function (): ProductVariant {
        offerStoreCurrency('USD', '0.001');
        $variant = cartVariant(150000);
        (new FixedPrice)->forceFill(['product_variant_id' => $variant->id, 'currency_code' => 'USD', 'price' => Money::ofMinor(199, 'USD')])->save();

        return $variant;
    });

    // Added in naira, then switched to dollars: the dollar price shown on switching is the one compared from then on.
    cartRequest('POST', '/lines', ['variant' => $variant->public_id], token: $token)->assertOk()->assertJsonPath('data.lines.0.unit_price.amount', 150000);
    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: '"2"')->assertOk()->assertJsonPath('data.lines.0.unit_price.amount', 199);

    // The rate and the base price move: the fixed dollar price doesn't, so nothing is flagged.
    $this->store->run(static function () use ($variant): void {
        DB::table('store_currencies')->update(['manual_rate' => '0.002']);
        $variant->forceFill(['price' => Money::ofMinor(170000, 'NGN')])->save();
    });
    cartRequest('GET', token: $token)->assertJsonPath('data.lines.0.price_changed', null);

    $this->store->run(static fn () => FixedPrice::query()->update(['price_amount' => 249]));
    cartRequest('GET', token: $token)->assertJsonPath('data.lines.0.price_changed', ['was' => ['amount' => 199, 'currency' => 'USD', 'formatted' => '$1.99'], 'now' => ['amount' => 249, 'currency' => 'USD', 'formatted' => '$2.49']]);
});

it('estimates tax to the country the shopper gave, or the store\'s own', function (): void {
    createStore('us-store', ['country_code' => 'US', 'currency_code' => 'USD', 'timezone' => 'America/New_York']);
    $token = newGuestCart('us-store');
    $variant = App\Landlord\Tenancy\Models\Tenant::query()->whereHas('domains', static fn ($query) => $query->where('domain', 'us-store.'.config('platform.domain')))->sole()->run(static function (): string {
        addTaxRate('Standard', 'US', null, '5', 'Sales tax');
        addTaxRate('Standard', 'DE', null, '19');

        return cartVariant(10000)->public_id;
    });
    cartRequest('POST', '/lines', ['variant' => $variant], token: $token, subdomain: 'us-store')->assertOk()
        ->assertJsonPath('data.tax', ['is_estimate' => true, 'mode' => 'exclusive', 'country_code' => 'US', 'chosen_country_code' => null, 'chosen_state_code' => null])
        ->assertJsonPath('data.totals.tax.amount', 500);

    cartRequest('PATCH', '', ['country_code' => 'de'], token: $token, ifMatch: '"2"', subdomain: 'us-store')->assertUnprocessable()->assertJsonValidationErrors('country_code');
    cartRequest('PATCH', '', ['country_code' => 'DE'], token: $token, ifMatch: '"2"', subdomain: 'us-store')->assertOk()
        ->assertJsonPath('data.tax', ['is_estimate' => true, 'mode' => 'exclusive', 'country_code' => 'DE', 'chosen_country_code' => 'DE', 'chosen_state_code' => null])
        ->assertJsonPath('data.totals.tax.amount', 1900)
        ->assertJsonPath('data.totals.total.amount', 11900);
});

it('reads a cart of 100 lines in as many queries as a cart of 1', function (): void {
    $counts = [];
    $this->store->run(static fn () => runningPromotion(['percent' => Decimal::of(10)]));

    foreach ([1, 100] as $size) {
        $token = newGuestCart();
        $this->store->run(static function () use ($size): void {
            $cart = App\Tenant\Cart\Models\Cart::query()->latest('id')->firstOrFail();

            foreach (range(1, $size) as $position) {
                $variant = cartVariant(1000 + $position, stock: $position % 2 === 0 ? 5 : null);
                $cart->lines()->create(['product_variant_id' => $variant->id, 'quantity' => 1, 'seen_price' => $variant->price, 'seen_price_source' => App\Tenant\Cart\Enums\SeenPriceSource::Converted]);
            }
        });

        $this->store->run(static function () use (&$counts, $size): void {
            $cart = App\Tenant\Cart\Models\Cart::query()->latest('id')->firstOrFail();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $priced = app(App\Tenant\Cart\Actions\PriceCart::class)->handle($cart);
            $counts[$size] = count(DB::getQueryLog());
            DB::disableQueryLog();

            expect($priced->lines)->toHaveCount($size);
        });
    }

    expect($counts[100])->toBe($counts[1]);
});
