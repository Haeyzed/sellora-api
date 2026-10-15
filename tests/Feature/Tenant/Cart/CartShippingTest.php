<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Promotions\Enums\PromotionType;
use Illuminate\Support\Facades\DB;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 13: shipping in the cart. The shopper says where they are (a country
 * and, optionally, a state code), sees the methods offered there for what
 * the cart holds, and chooses one; its price joins the totals, after
 * free-shipping promotions and with the shipping tax class. All of it is an
 * estimate until checkout has the full address. A cart needing shipping
 * can't check out until an offered method is chosen.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);

    [$this->owner, $this->location] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        offerStoreCurrency('USD', '0.001');

        return [$owner, StockLocation::query()->sole()->public_id];
    });

    $this->nigeria = shippingZone('Nigeria', ['NG'], subdomain: 'cart-store');
    $this->lagos = shippingZone('Lagos', ['NG-LA'], subdomain: 'cart-store');
    $this->standard = cartShippingMethod(['zone' => $this->nigeria, 'name' => ['en' => 'Standard'], 'pricing' => 'order_value', 'rates' => [
        ['from' => 0, 'to' => 1000000, 'amount' => 150000],
        ['from' => 1000000, 'to' => null, 'amount' => 50000, 'currency_amounts' => [['currency' => 'USD', 'amount' => 75]]],
    ]]);
    $this->sameDay = cartShippingMethod(['zone' => $this->lagos, 'name' => ['en' => 'Same day'], 'fulfilment' => 'own_delivery', 'rates' => [['from' => 0, 'to' => null, 'amount' => 200000]]]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('takes a state of the cart\'s country, and clears it when the country changes', function (): void {
    $this->store->run(static function (): void {
        addTaxRate('Standard', 'NG', null, '7.5');
        addTaxRate('Standard', 'NG', 'LA', '10');
    });
    $token = shippingCart([[110000, 1, true]]);
    expect(cartRequest('GET', token: $token)->json('data.totals.tax.amount'))->toBe(7674);

    // The state's tax rate replaces the country's in the estimate.
    cartRequest('PATCH', '', ['state_code' => 'la'], token: $token, ifMatch: cartVersion($token))->assertOk()->assertJsonPath('data.tax.chosen_state_code', 'LA')->assertJsonPath('data.shipping.state_code', 'LA')->assertJsonPath('data.totals.tax.amount', 10000);
    cartRequest('PATCH', '', ['state_code' => 'CA'], token: $token, ifMatch: cartVersion($token))->assertUnprocessable()->assertJsonPath('code', 'state_not_in_country')->assertJsonValidationErrors('state_code');
    cartRequest('PATCH', '', ['country_code' => 'US', 'state_code' => 'CA'], token: $token, ifMatch: cartVersion($token))->assertOk()->assertJsonPath('data.shipping.state_code', 'CA')->assertJsonPath('data.shipping.country_code', 'US');
    cartRequest('PATCH', '', ['country_code' => 'DE'], token: $token, ifMatch: cartVersion($token))->assertOk()->assertJsonPath('data.shipping.state_code', null);
    // A country with no states list takes none.
    cartRequest('PATCH', '', ['state_code' => 'BE'], token: $token, ifMatch: cartVersion($token))->assertUnprocessable()->assertJsonPath('code', 'state_not_in_country');
    cartRequest('PATCH', '', ['state_code' => null], token: $token, ifMatch: cartVersion($token))->assertOk();

    expect($this->store->run(static fn (): array => (array) DB::table('carts')->first(['destination_country_code', 'destination_state_code'])))->toBe(['destination_country_code' => 'DE', 'destination_state_code' => null]);
});

it('lists the methods offered where the shopper is, priced by the value after discounts of the lines that ship', function (): void {
    $token = shippingCart([[600000, 1, true], [900000, 1, false]]);

    $options = cartRequest('GET', '/shipping-options', token: $token)->assertOk();
    // The ₦9,000 that doesn't ship counts for nothing: ₦6,000 is in the first bracket.
    expect($options->json('data'))->toMatchArray(['required' => true, 'reason' => null, 'weight_grams' => 500])
        ->and($options->json('data.value.amount'))->toBe(600000)
        ->and(array_column($options->json('data.options'), 'name'))->toBe(['Standard'])
        ->and($options->json('data.options.0.amount.amount'))->toBe(150000);

    // In Lagos, Lagos's methods instead of Nigeria's.
    cartRequest('PATCH', '', ['state_code' => 'LA'], token: $token, ifMatch: cartVersion($token))->assertOk();
    expect(array_column(cartRequest('GET', '/shipping-options', token: $token)->json('data.options'), 'name'))->toBe(['Same day']);

    // A discount brings the value under a bracket's start.
    cartRequest('PATCH', '', ['state_code' => null], token: $token, ifMatch: cartVersion($token))->assertOk();
    $big = shippingCart([[1200000, 1, true]]);
    expect(cartRequest('GET', '/shipping-options', token: $big)->json('data.options.0.amount.amount'))->toBe(50000);
    $this->store->run(static fn () => runningPromotion(['percent' => Decimal::of(25)]));
    expect(cartRequest('GET', '/shipping-options', token: $big)->json('data.options.0.amount.amount'))->toBe(150000);

    // Nothing that ships, nothing needed.
    $digital = shippingCart([[5000, 2, false]]);
    expect(cartRequest('GET', '/shipping-options', token: $digital)->json('data'))->toMatchArray(['required' => false, 'options' => []])
        ->and(cartRequest('GET', token: $digital)->json('data.can_check_out'))->toBeTrue();
});

it('lets the shopper choose only an offered method, which then joins the totals, and checks out only once one is chosen', function (): void {
    $token = shippingCart([[600000, 2, true]]);

    $cart = cartRequest('GET', token: $token)->assertOk();
    expect($cart->json('data.can_check_out'))->toBeFalse()->and($cart->json('data.shipping.method'))->toBeNull();

    foreach ([$this->sameDay, '01jzzzzzzzzzzzzzzzzzzzzzzz', 'nope'] as $notOffered) {
        cartRequest('PATCH', '', ['shipping_method' => $notOffered], token: $token, ifMatch: cartVersion($token))->assertUnprocessable()->assertJsonPath('code', 'shipping_method_not_offered')->assertJsonValidationErrors('shipping_method');
    }

    $chosen = cartRequest('PATCH', '', ['shipping_method' => $this->standard], token: $token, ifMatch: cartVersion($token))->assertOk();
    expect($chosen->json('data.shipping'))->toMatchArray(['required' => true, 'method' => ['id' => $this->standard, 'name' => 'Standard', 'fulfilment' => 'ship'], 'chosen_method_unavailable' => false, 'calculated_at_checkout' => false])
        ->and($chosen->json('data.shipping.amount.amount'))->toBe(50000)
        ->and($chosen->json('data.shipping.discount.amount'))->toBe(0)
        ->and($chosen->json('data.totals.shipping.amount'))->toBe(50000)
        ->and($chosen->json('data.totals.total.amount'))->toBe(1250000)
        ->and($chosen->json('data.can_check_out'))->toBeTrue();

    // In another currency, the exact amount set for it.
    $usd = cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: cartVersion($token))->assertOk();
    expect([$usd->json('data.shipping.amount.amount'), $usd->json('data.totals.shipping.amount')])->toBe([75, 75]);

    // Moving to where it isn't offered keeps the choice, says so, and stops checkout.
    $moved = cartRequest('PATCH', '', ['country_code' => 'US'], token: $token, ifMatch: cartVersion($token))->assertOk();
    expect($moved->json('data.shipping'))->toMatchArray(['reason' => 'no_zone', 'chosen_method_unavailable' => true, 'amount' => null])
        ->and($moved->json('data.totals.shipping.amount'))->toBe(0)
        ->and($moved->json('data.can_check_out'))->toBeFalse();

    cartRequest('PATCH', '', ['country_code' => null, 'shipping_method' => null], token: $token, ifMatch: cartVersion($token))->assertOk()->assertJsonPath('data.shipping.method', null);
});

it('stops offering a method once it is switched off or archived, and says so on carts that chose it', function (): void {
    $token = shippingCart([[600000, 1, true]]);
    cartRequest('PATCH', '', ['shipping_method' => $this->standard], token: $token, ifMatch: cartVersion($token))->assertOk();

    shippingRequest('POST', "/methods/{$this->standard}/archive", subdomain: 'cart-store')->assertOk();

    expect(cartRequest('GET', token: $token)->json('data.shipping'))->toMatchArray(['chosen_method_unavailable' => true, 'amount' => null])
        ->and(cartRequest('GET', '/shipping-options', token: $token)->json('data.options'))->toBe([]);
});

it('takes free-shipping promotions off the chosen shipping, and taxes the rest at the shipping tax class', function (): void {
    $this->store->run(static fn () => addTaxRate('Standard', 'NG', null, '7.5'));
    $token = shippingCart([[600000, 1, true]]);
    cartRequest('PATCH', '', ['shipping_method' => $this->standard], token: $token, ifMatch: cartVersion($token))->assertOk();

    // The store's prices include tax: ₦1,500 of shipping holds ₦104.65 of it.
    $taxed = cartRequest('GET', token: $token);
    expect($taxed->json('data.totals.shipping.amount'))->toBe(150000)
        ->and($taxed->json('data.totals.tax.amount'))->toBe(41860 + 10465)
        ->and($taxed->json('data.totals.total.amount'))->toBe(750000);

    $this->store->run(static fn () => runningPromotion(['type' => PromotionType::FreeShipping, 'applies_to' => null, 'percent' => null, 'title' => ['en' => 'Free delivery']]));
    $free = cartRequest('GET', token: $token);
    expect($free->json('data.shipping.amount.amount'))->toBe(150000)
        ->and($free->json('data.shipping.discount.amount'))->toBe(150000)
        ->and($free->json('data.discounts.0'))->toMatchArray(['label' => 'Free delivery', 'class' => 'shipping'])
        ->and([$free->json('data.totals.shipping.amount'), $free->json('data.totals.tax.amount'), $free->json('data.totals.total.amount')])->toBe([0, 41860, 600000]);
});

it('estimates tax at a chosen pickup\'s address', function (): void {
    $this->store->run(static function (): void {
        addTaxRate('Standard', 'NG', null, '7.5');
        addTaxRate('Standard', 'NG', 'LA', '10');
    });
    $pickup = cartShippingMethod(['fulfilment' => 'pickup', 'name' => ['en' => 'Collect in Ikeja'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 0]],
        'pickup' => ['stock_location' => $this->location, 'country_code' => 'NG', 'state_code' => 'LA', 'address_line1' => '1 Allen Avenue', 'city' => 'Ikeja']]);
    $token = shippingCart([[110000, 1, true]]);

    expect(cartRequest('GET', token: $token)->json('data.totals.tax.amount'))->toBe(7674);

    $collected = cartRequest('PATCH', '', ['shipping_method' => $pickup], token: $token, ifMatch: cartVersion($token))->assertOk();
    expect($collected->json('data.totals.tax.amount'))->toBe(10000)
        ->and($collected->json('data.tax.country_code'))->toBe('NG')
        ->and($collected->json('data.shipping.amount.amount'))->toBe(0)
        ->and($collected->json('data.can_check_out'))->toBeTrue();
});

it('prices a cart with its shipping in the same number of queries, with 1 line or 100', function (): void {
    $counts = [];
    $this->store->run(static fn () => runningPromotion(['type' => PromotionType::FreeShipping, 'applies_to' => null, 'percent' => null]));

    foreach ([1, 100] as $size) {
        newGuestCart();
        $this->store->run(function () use ($size): void {
            $cart = App\Tenant\Cart\Models\Cart::query()->latest('id')->firstOrFail();
            $cart->forceFill(['shipping_method_id' => App\Tenant\Shipping\Models\ShippingMethod::query()->where('public_id', $this->standard)->value('id')])->save();

            foreach (range(1, $size) as $position) {
                $variant = cartVariant(1000 + $position, stock: $position % 2 === 0 ? 5 : null);
                $variant->forceFill(['requires_shipping' => $position % 3 !== 0, 'weight_grams' => $position % 3 !== 0 ? 100 : null])->save();
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

            expect($priced->lines)->toHaveCount($size)
                ->and($priced->shipping->option?->amount?->minorAmount())->toBe(150000)
                ->and($priced->shipping->discount?->minorAmount())->toBe(150000);
        });
    }

    expect($counts[100])->toBe($counts[1]);
});
