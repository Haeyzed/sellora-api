<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use App\Tenant\Shipping\Actions\QuoteCarrierRates;
use App\Tenant\Shipping\ShippingCarrierRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Shipping\FakeCarrier;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 13: live carrier rates, through the ShippingCarrier contract that
 * integrations implement; core names no carrier. A carrier is used only
 * while its integration is enabled for the store, and only once the full
 * address is known: before that, its methods are "calculated at checkout".
 * A carrier that fails, or answers later than the timeout, gives way to the
 * method's backup price, or the method isn't offered. Good answers are
 * reused briefly under a hashed key; failures never are.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shipping-store', ['country_code' => 'NG']);
    FakeCarrier::reset();
    Cache::flush();

    app(FeatureRegistry::class)->register(new FeatureDefinition(FakeCarrier::FEATURE_KEY, FeatureKind::Integration));
    app(ShippingCarrierRegistry::class)->register(FakeCarrier::FEATURE_KEY, FakeCarrier::class);
    $this->features = (new FakeFeatureSource)->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(entitled: [FakeCarrier::FEATURE_KEY]));
    app()->instance(FeatureSource::class, $this->features);

    [$this->owner, $this->parcel] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        offerStoreCurrency('USD', '0.001');
        StoreSettings::query()->sole()->forceFill(['address_line1' => '1 Warehouse Road', 'address_city' => 'Ikeja', 'address_state_code' => 'LA', 'address_country_code' => 'NG'])->save();

        $parcel = cartVariant(price: 100000, name: 'Lamp');
        $parcel->forceFill(['requires_shipping' => true, 'weight_grams' => 400])->save();

        return [$owner, $parcel->public_id];
    });

    $this->zone = shippingZone('Nigeria', ['NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('sets up a carrier method only for a carrier the store can use, with one of its services, priced by the carrier rather than brackets', function (): void {
    $method = shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Express'], 'fulfilment' => 'ship', 'pricing' => 'carrier', 'carrier' => 'fake_carrier', 'carrier_service' => 'express', 'backup' => 300000, 'currency_amounts' => [['currency' => 'USD', 'backup' => 350]]])
        ->assertCreated()
        ->json('data');

    expect($method)->toMatchArray(['pricing' => 'carrier', 'carrier' => 'fake_carrier', 'carrier_service' => 'express', 'rates' => [], 'warnings' => []])
        ->and($method['backup']['amount'])->toBe(300000)
        ->and($method['currency_amounts'][0]['backup']['amount'])->toBe(350);

    $base = ['zone' => $this->zone, 'name' => ['en' => 'Bad'], 'fulfilment' => 'ship', 'pricing' => 'carrier', 'carrier' => 'fake_carrier', 'carrier_service' => 'standard'];
    shippingRequest('POST', '/methods', [...$base, 'carrier' => 'nobody'])->assertUnprocessable()->assertJsonValidationErrors('carrier');
    shippingRequest('POST', '/methods', [...$base, 'carrier_service' => 'overnight'])->assertUnprocessable()->assertJsonValidationErrors('carrier_service');
    shippingRequest('POST', '/methods', [...$base, 'rates' => [['from' => 0, 'to' => null, 'amount' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('rates');
    shippingRequest('POST', '/methods', [...$base, 'fulfilment' => 'own_delivery'])->assertUnprocessable()->assertJsonValidationErrors('pricing');
    shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Flat'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'carrier' => 'fake_carrier', 'backup' => 1, 'rates' => [['from' => 0, 'to' => null, 'amount' => 1]]])->assertUnprocessable()->assertJsonValidationErrors(['carrier', 'backup']);
    shippingRequest('PATCH', "/methods/{$method['id']}", ['carrier_service' => 'standard', 'backup' => null])->assertOk()->assertJsonPath('data.carrier_service', 'standard')->assertJsonPath('data.backup', null);

    // The database holds the same rules.
    $this->store->run(static function (): void {
        $carrier = static fn () => DB::table('shipping_methods')->where('pricing', 'carrier');

        expect(static fn () => $carrier()->update(['fulfilment' => 'own_delivery']))->toThrow(QueryException::class)
            ->and(static fn () => $carrier()->update(['carrier_service' => null]))->toThrow(QueryException::class)
            ->and(static fn () => $carrier()->update(['pricing' => 'flat']))->toThrow(QueryException::class)
            ->and(static fn () => $carrier()->update(['backup_amount' => -1]))->toThrow(QueryException::class);
    });

    // Not while its integration is off for the store.
    $this->features->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot());
    app(Features::class)->forget((string) $this->store->getTenantKey());
    shippingRequest('POST', '/methods', $base)->assertUnprocessable()->assertJsonValidationErrors('carrier');
});

it('shows a carrier method as calculated at checkout until the full address is known, and lets the cart check out with it', function (): void {
    $method = carrierMethod();
    FakeCarrier::$rates = ['standard' => [250000, 'NGN']];

    expect(carrierOptions(withAddress: false)[0])->toMatchArray(['id' => $method, 'amount' => null, 'calculated_at_checkout' => true])
        ->and(FakeCarrier::$asked)->toBe([]);

    $token = newGuestCart('shipping-store');
    cartRequest('POST', '/lines', ['variant' => $this->parcel], token: $token, subdomain: 'shipping-store')->assertOk();
    $cart = cartRequest('PATCH', '', ['shipping_method' => $method], token: $token, ifMatch: '"2"', subdomain: 'shipping-store')->assertOk();

    expect($cart->json('data.shipping'))->toMatchArray(['calculated_at_checkout' => true, 'amount' => null, 'chosen_method_unavailable' => false])
        ->and($cart->json('data.totals.shipping.amount'))->toBe(0)
        ->and($cart->json('data.can_check_out'))->toBeTrue()
        ->and(FakeCarrier::$asked)->toBe([]);
});

it('quotes live with the full address, in the basket\'s currency or converted from the base currency', function (): void {
    $method = carrierMethod();

    FakeCarrier::$rates = ['standard' => [250000, 'NGN'], 'express' => [500000, 'NGN']];
    expect(carrierOptions()[0])->toMatchArray(['id' => $method, 'calculated_at_checkout' => false, 'is_free' => false])
        ->and(carrierOptions()[0]['amount']['amount'])->toBe(250000);

    $asked = FakeCarrier::$asked[0];
    expect([$asked->origin->line1, $asked->origin->city, $asked->origin->countryCode])->toBe(['1 Warehouse Road', 'Ikeja', 'NG'])
        ->and([$asked->destination->line1, $asked->destination->postalCode, $asked->destination->stateCode])->toBe(['5 Allen Avenue', '100271', 'LA'])
        ->and($asked->parcels[0]->weightGrams)->toBe(800)
        ->and($asked->currency)->toBe('NGN');

    // ₦2,500 is $2.50 at the store's rate; an answer in the basket's currency is used as it is; one in any other isn't trusted.
    expect(carrierOptions('USD')[0]['amount']['amount'])->toBe(250);
    Cache::flush();
    FakeCarrier::$rates = ['standard' => [399, 'USD']];
    expect(carrierOptions('USD')[0]['amount']['amount'])->toBe(399);
    Cache::flush();
    FakeCarrier::$rates = ['standard' => [399, 'EUR']];
    expect(carrierOptions('USD'))->toBe([]);

    // A service the carrier doesn't offer there isn't offered.
    Cache::flush();
    FakeCarrier::$rates = ['express' => [500000, 'NGN']];
    expect(carrierOptions())->toBe([]);
});

it('falls back to the backup price when the carrier fails, is too slow, or isn\'t usable, and leaves the method out without one', function (): void {
    $backedUp = carrierMethod(['name' => ['en' => 'Backed up'], 'backup' => 300000, 'currency_amounts' => [['currency' => 'USD', 'backup' => 350]]]);
    carrierMethod(['name' => ['en' => 'No backup'], 'carrier_service' => 'express']);

    FakeCarrier::$fails = true;
    $failed = carrierOptions();
    expect(array_column($failed, 'id'))->toBe([$backedUp])
        ->and($failed[0]['amount']['amount'])->toBe(300000)
        ->and(carrierOptions('USD')[0]['amount']['amount'])->toBe(350);

    // A failure is never kept: the carrier is asked again, and answers now.
    FakeCarrier::$fails = false;
    FakeCarrier::$rates = ['standard' => [250000, 'NGN'], 'express' => [500000, 'NGN']];
    expect(array_column(carrierOptions(), 'amount'))->toHaveCount(2)
        ->and(carrierOptions()[0]['amount']['amount'])->toBe(250000);

    // Later than the timeout counts as no answer.
    Cache::flush();
    config(['shipping.carrier_timeout_seconds' => 1]);
    FakeCarrier::$delaySeconds = 2;
    expect(array_column(carrierOptions(street: '7 Allen Avenue'), 'id'))->toBe([$backedUp]);
    FakeCarrier::$delaySeconds = 0;

    // Its integration switched off for the store: the carrier isn't asked at all.
    Cache::flush();
    FakeCarrier::$asked = [];
    $this->features->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot());
    app(Features::class)->forget((string) $this->store->getTenantKey());
    expect(array_column(carrierOptions(), 'id'))->toBe([$backedUp])
        ->and(FakeCarrier::$asked)->toBe([]);
});

it('reuses a good quote for the same parcel, addresses and currency, under a key with no readable address', function (): void {
    carrierMethod();
    carrierMethod(['name' => ['en' => 'Express'], 'carrier_service' => 'express']);
    FakeCarrier::$rates = ['standard' => [250000, 'NGN'], 'express' => [500000, 'NGN']];

    // One store context, as one worker serving requests with a shared cache.
    $this->store->run(static function (): void {
        $methods = App\Tenant\Shipping\Models\ShippingMethod::query()->with('currencyAmounts')->orderBy('id')->get()->all();
        $rates = app(App\Tenant\Pricing\Actions\FindExchangeRates::class)->handle(['NGN', 'USD']);
        $quote = static fn (string $street = '5 Allen Avenue', int $grams = 800, string $currency = 'NGN'): array => app(QuoteCarrierRates::class)->handle(
            $methods,
            new App\Tenant\Shipping\Data\Parcel($grams),
            new App\Tenant\Shipping\Data\CarrierAddress('NG', 'LA', '100271', 'Ikeja', $street),
            $rates[$currency],
        );

        expect($quote())->toHaveCount(2)
            ->and($quote())->toHaveCount(2)
            // One question for both methods, and none the second time.
            ->and(FakeCarrier::$asked)->toHaveCount(1);

        $quote(street: '9 Allen Avenue');
        $quote(grams: 1200);
        $quote(currency: 'USD');
        expect(FakeCarrier::$asked)->toHaveCount(4);

        // A failure is never kept.
        FakeCarrier::$fails = true;
        $quote(street: '11 Allen Avenue');
        $quote(street: '11 Allen Avenue');
        expect(FakeCarrier::$asked)->toHaveCount(6);

        // The tests' cache is an array store: every key it holds for quotes is a hash, never an address.
        $keys = array_keys((fn (): array => $this->storage)->call(Cache::getStore()));
        $quoteKeys = array_values(array_filter($keys, static fn (string $key): bool => str_contains($key, QuoteCarrierRates::CACHE_PREFIX)));

        expect($quoteKeys)->toHaveCount(4)
            ->and(array_filter($quoteKeys, static fn (string $key): bool => preg_match('/'.preg_quote(QuoteCarrierRates::CACHE_PREFIX, '/').'[0-9a-f]{64}$/', $key) !== 1))->toBe([])
            ->and(implode(' ', $keys))->not->toContain('Allen')->not->toContain('100271')->not->toContain('Ikeja');
    });
});

it('makes a carrier method free past its threshold without asking the carrier', function (): void {
    carrierMethod(['free_over' => 150000]);
    FakeCarrier::$rates = ['standard' => [250000, 'NGN']];

    expect(carrierOptions(lamps: 1)[0]['amount']['amount'])->toBe(250000);
    FakeCarrier::$asked = [];

    expect(carrierOptions(lamps: 2)[0])->toMatchArray(['is_free' => true, 'calculated_at_checkout' => false])
        ->and(carrierOptions(lamps: 2)[0]['amount']['amount'])->toBe(0)
        ->and(FakeCarrier::$asked)->toBe([]);
});
