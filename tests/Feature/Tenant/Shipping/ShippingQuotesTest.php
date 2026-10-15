<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockLocation;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 13: what a basket pays for shipping. Only lines that ship count, by
 * weight and by value; a method whose brackets don't cover the basket isn't
 * offered; "free over" makes it free. Prices are exact amounts for the
 * currency, or the base amount converted at the store's rate and rounded
 * half-up to the currency's decimals (0 for JPY, 3 for KWD).
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shipping-store', ['country_code' => 'NG']);

    [$this->owner, $this->viewer, $this->location, $this->parcel, $this->ebook] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $role = Role::findOrCreate('Dispatcher', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('shipping.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        offerStoreCurrency('USD', '0.001');
        offerStoreCurrency('JPY', '0.0975');
        offerStoreCurrency('KWD', '0.0002035');

        $parcel = cartVariant(price: 100000, name: 'Lamp');
        $parcel->forceFill(['requires_shipping' => true, 'weight_grams' => 400])->save();
        $ebook = cartVariant(price: 50000, name: 'E-book');
        $ebook->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();

        return [$owner, $viewer, StockLocation::query()->sole()->public_id, $parcel->public_id, $ebook->public_id];
    });

    $this->nigeria = shippingZone('Nigeria', ['NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('prices by the weight of the lines that ship, and leaves a method out where its brackets don\'t reach', function (): void {
    quotedMethod(['zone' => $this->nigeria, 'pricing' => 'weight', 'rates' => [
        ['from' => 0, 'to' => 1200, 'amount' => 50000],
        ['from' => 1200, 'to' => 4800, 'amount' => 90000],
        ['from' => 10000, 'to' => null, 'amount' => 200000],
    ]]);

    $priceFor = fn (int $lamps): ?int => shippingQuoteFor([[$this->parcel, $lamps], [$this->ebook, 3]])['options'][0]['amount']['amount'] ?? null;

    // 400 g a lamp; the e-books weigh nothing and don't ship. A bracket includes where it starts, not where it ends.
    expect([$priceFor(1), $priceFor(2), $priceFor(3), $priceFor(11), $priceFor(12), $priceFor(24), $priceFor(25)])
        ->toBe([50000, 50000, 90000, 90000, null, null, 200000])
        ->and(shippingQuoteFor([[$this->parcel, 13]])['weight_grams'])->toBe(5200);
});

it('prices by the value of the lines that ship, and is free once they reach its threshold', function (): void {
    quotedMethod(['zone' => $this->nigeria, 'pricing' => 'order_value', 'free_over' => 500000, 'rates' => [
        ['from' => 0, 'to' => 200000, 'amount' => 150000],
        ['from' => 200000, 'to' => null, 'amount' => 100000],
    ]]);

    $quote = fn (int $lamps): array => shippingQuoteFor([[$this->parcel, $lamps], [$this->ebook, 20]]);

    // A lamp is 100000; the e-books are worth 1000000 but don't ship, so they count for nothing.
    expect($quote(1)['value']['amount'])->toBe(100000)
        ->and([$quote(1)['options'][0]['amount']['amount'], $quote(2)['options'][0]['amount']['amount'], $quote(4)['options'][0]['amount']['amount']])->toBe([150000, 100000, 100000])
        ->and([$quote(4)['options'][0]['is_free'], $quote(5)['options'][0]['is_free'], $quote(5)['options'][0]['amount']['amount']])->toBe([false, true, 0]);
});

it('uses an exact price for a currency, and otherwise converts the base price, rounded half-up to the currency\'s decimals', function (): void {
    quotedMethod(['zone' => $this->nigeria, 'free_over' => 1000000, 'currency_amounts' => [['currency' => 'USD', 'free_over' => 300]], 'rates' => [
        ['from' => 0, 'to' => null, 'amount' => 250000, 'currency_amounts' => [['currency' => 'USD', 'amount' => 299]]],
    ]]);

    $option = fn (string $currency, int $lamps = 1): array => shippingQuoteFor([[$this->parcel, $lamps]], currency: $currency)['options'][0];

    // 250000 kobo is ₦2,500: $2.99 exactly; ¥243.75 → ¥244; KD 0.50875 → KD 0.509.
    expect([$option('NGN')['amount']['amount'], $option('USD')['amount']['amount'], $option('JPY')['amount']['amount'], $option('KWD')['amount']['amount']])
        ->toBe([250000, 299, 244, 509]);

    // The exact USD threshold ($3) applies, not ₦10,000 converted ($10): three lamps are $300 worth.
    expect([$option('USD', 3)['is_free'], $option('JPY', 3)['is_free'], $option('JPY', 10)['is_free']])->toBe([true, false, true]);
});

it('needs no shipping when nothing ships, and offers only pickups where no zone reaches', function (): void {
    quotedMethod(['zone' => $this->nigeria, 'rates' => [['from' => 0, 'to' => null, 'amount' => 1000]]]);
    quotedMethod(['fulfilment' => 'pickup', 'name' => ['en' => 'Collect in Lagos'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 0]],
        'pickup' => ['stock_location' => $this->location, 'country_code' => 'US', 'address_line1' => '1 Main St', 'city' => 'Albany']]);

    expect(shippingQuoteFor([[$this->ebook, 2]]))->toMatchArray(['required' => false, 'options' => [], 'reason' => null, 'weight_grams' => 0]);

    $abroad = shippingQuoteFor([[$this->parcel, 1]], country: 'US');
    expect($abroad)->toMatchArray(['required' => true, 'reason' => 'no_zone'])
        ->and(array_column($abroad['options'], 'name'))->toBe(['Collect in Lagos'])
        ->and($abroad['options'][0]['pickup'])->toMatchArray(['country_code' => 'US', 'city' => 'Albany']);

    // Pickups are only for shoppers in their country.
    expect(array_column(shippingQuoteFor([[$this->parcel, 1]])['options'], 'name'))->toBe(['Method'])
        ->and(shippingQuoteFor([[$this->parcel, 1]], country: 'JP'))->toMatchArray(['required' => true, 'reason' => 'no_zone', 'options' => []]);
});

it('offers a zone\'s live methods by position, pickups last, and a state\'s zone instead of its country\'s', function (): void {
    $lagos = shippingZone('Lagos', ['NG-LA']);
    quotedMethod(['zone' => $this->nigeria, 'name' => ['en' => 'Nationwide'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 3000]]]);
    quotedMethod(['zone' => $lagos, 'name' => ['en' => 'Lagos courier'], 'position' => 2, 'rates' => [['from' => 0, 'to' => null, 'amount' => 1000]]]);
    quotedMethod(['zone' => $lagos, 'name' => ['en' => 'Lagos same day'], 'fulfilment' => 'own_delivery', 'position' => 1, 'rates' => [['from' => 0, 'to' => null, 'amount' => 2000]]]);
    quotedMethod(['fulfilment' => 'pickup', 'name' => ['en' => 'Collect'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 0]],
        'pickup' => ['stock_location' => $this->location, 'country_code' => 'NG', 'address_line1' => '1 Allen Avenue', 'city' => 'Ikeja']]);
    $off = quotedMethod(['zone' => $lagos, 'name' => ['en' => 'Switched off'], 'is_active' => false, 'rates' => [['from' => 0, 'to' => null, 'amount' => 1]]]);
    $gone = quotedMethod(['zone' => $lagos, 'name' => ['en' => 'Archived'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 1]]]);
    shippingRequest('POST', "/methods/{$gone}/archive")->assertOk();

    expect(array_column(shippingQuoteFor([[$this->parcel, 1]], state: 'LA')['options'], 'name'))->toBe(['Lagos same day', 'Lagos courier', 'Collect'])
        ->and(array_column(shippingQuoteFor([[$this->parcel, 1]], state: 'AB')['options'], 'name'))->toBe(['Nationwide', 'Collect'])
        ->and(shippingQuoteFor([[$this->parcel, 1]], state: 'LA')['options'][0])->toMatchArray(['fulfilment' => 'own_delivery', 'calculated_at_checkout' => false, 'is_free' => false, 'pickup' => null])
        ->and($off)->toBeString();
});

it('previews only in currencies the store offers, for staff who may see shipping', function (): void {
    shippingRequest('POST', '/quotes', ['country_code' => 'NG', 'currency' => 'EUR', 'lines' => [['variant' => $this->parcel, 'quantity' => 1]]])->assertUnprocessable()->assertJsonPath('code', 'currency_not_offered');
    shippingRequest('POST', '/quotes', ['country_code' => 'NG', 'lines' => [['variant' => $this->parcel, 'quantity' => 1]]], as: $this->viewer)->assertOk();

    $nobody = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());
    shippingRequest('POST', '/quotes', ['country_code' => 'NG', 'lines' => [['variant' => $this->parcel, 'quantity' => 1]]], as: $nobody)->assertForbidden();
});
