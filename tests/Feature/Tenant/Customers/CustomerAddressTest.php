<?php

declare(strict_types=1);

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Data\TaxAddress;
use Illuminate\Database\QueryException;
use Tests\Support\TruncatesCentralDatabase;

/*
 * A customer's saved addresses (section 3.3 and 10): checked by the
 * address country's rules, never more than 20, only ever their own, and
 * copied onto orders rather than linked.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    seedAddressFormatCountries();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    $this->customer = firstStoreCustomer();
});

afterEach(function (): void {
    deleteAllStores();
});

it('saves, lists, shows, replaces and deletes a customer\'s own addresses', function (): void {
    $id = customerRequest('POST', 'account/addresses', lagosAddress(['label' => 'Home', 'state' => 'la', 'phone' => '0803 123 4567']), $this->customer)
        ->assertCreated()
        ->assertJsonPath('data.label', 'Home')
        ->assertJsonPath('data.state', 'LA')
        ->assertJsonPath('data.phone', '+2348031234567')
        ->assertJsonPath('data.is_default_shipping', false)
        ->json('data.id');

    customerRequest('GET', 'account/addresses', as: $this->customer)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    customerRequest('GET', "account/addresses/{$id}", as: $this->customer)->assertOk()->assertJsonPath('data.line1', '12 Admiralty Way');

    customerRequest('PUT', "account/addresses/{$id}", tokyoAddress(), $this->customer)
        ->assertOk()
        ->assertJsonPath('data.country', 'JP')
        ->assertJsonPath('data.state', '13')
        ->assertJsonPath('data.label', null)
        ->assertJsonPath('data.city', null);

    customerRequest('DELETE', "account/addresses/{$id}", as: $this->customer)->assertNoContent();
    customerRequest('GET', "account/addresses/{$id}", as: $this->customer)->assertNotFound();
});

it('requires only the fields the address country uses', function (array $address, ?string $field): void {
    $response = customerRequest('POST', 'account/addresses', $address, $this->customer);

    $field === null ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'Japan needs a prefecture' => [tokyoAddress(['state' => null]), 'state'],
    'Japan needs a postcode' => [tokyoAddress(['postal_code' => null]), 'postal_code'],
    'but no city' => [tokyoAddress(['city' => null]), null],
    'Nigeria needs a city' => [lagosAddress(['city' => null]), 'city'],
    'but no postcode' => [lagosAddress(['postal_code' => null]), null],
    'nor a state' => [lagosAddress(['state' => null]), null],
    'a state Nigeria doesn\'t have' => [lagosAddress(['state' => 'CA']), 'state'],
    'a postcode not in Nigeria\'s format' => [lagosAddress(['postal_code' => 'SW1A 1AA']), 'postal_code'],
    'a UK postcode' => [londonAddress(), null],
    'not a UK postcode' => [londonAddress(['postal_code' => '106104']), 'postal_code'],
    'a UK county, in any case' => [londonAddress(['state' => 'lnd']), null],
    'a county the UK doesn\'t have' => [londonAddress(['state' => 'ZZZ']), 'state'],
    'the US needs a state' => [californiaAddress(['state' => null]), 'state'],
    'a state the US doesn\'t have' => [californiaAddress(['state' => 'ZZ']), 'state'],
    // Germany's states aren't in the address data: any short text, or none.
    'a German state as free text' => [berlinAddress(['state' => 'Berlin']), null],
    'no German state' => [berlinAddress(), null],
    'a German state too long to be one' => [berlinAddress(['state' => str_repeat('x', 101)]), 'state'],
    'a country we don\'t support' => [lagosAddress(['country' => 'XX']), 'country'],
    'a phone that isn\'t one' => [lagosAddress(['phone' => '12345']), 'phone'],
    'no recipient' => [lagosAddress(['recipient_name' => '']), 'recipient_name'],
]);

it('reads a national phone number in the address country\'s format', function (): void {
    customerRequest('POST', 'account/addresses', londonAddress(['phone' => '020 7946 0958']), $this->customer)
        ->assertCreated()
        ->assertJsonPath('data.phone', '+442079460958')
        ->assertJsonPath('data.postal_code', 'SW1A 1AA');
});

it('keeps one default delivery and one default billing address', function (): void {
    $first = customerRequest('POST', 'account/addresses', lagosAddress(['is_default_shipping' => true, 'is_default_billing' => true]), $this->customer)
        ->assertJsonPath('data.is_default_shipping', true)
        ->assertJsonPath('data.is_default_billing', true)
        ->json('data.id');
    $second = customerRequest('POST', 'account/addresses', londonAddress(['is_default_shipping' => true]), $this->customer)->json('data.id');

    $listed = collect(customerRequest('GET', 'account/addresses', as: $this->customer)->json('data'))->keyBy('id');
    expect($listed[$first])->toMatchArray(['is_default_shipping' => false, 'is_default_billing' => true])
        ->and($listed[$second])->toMatchArray(['is_default_shipping' => true, 'is_default_billing' => false]);

    customerRequest('PUT', "account/addresses/{$first}", lagosAddress(['is_default_billing' => false]), $this->customer)->assertJsonPath('data.is_default_billing', false);
    customerRequest('DELETE', "account/addresses/{$second}", as: $this->customer)->assertNoContent();

    expect($this->store->run(fn (): array => $this->customer->refresh()->only(['default_shipping_address_id', 'default_billing_address_id'])))
        ->toBe(['default_shipping_address_id' => null, 'default_billing_address_id' => null]);
});

it('never lets a default point at another customer\'s address', function (): void {
    $theirs = $this->store->run(static fn (): CustomerAddress => CustomerAddress::factory()->create());

    expect(fn () => $this->store->run(fn () => $this->customer->forceFill(['default_shipping_address_id' => $theirs->id])->save()))
        ->toThrow(QueryException::class, 'customers_default_shipping_address_foreign');
});

it('finds only the customer\'s own addresses', function (): void {
    $theirs = $this->store->run(static fn (): string => CustomerAddress::factory()->create()->public_id);

    customerRequest('GET', 'account/addresses', as: $this->customer)->assertOk()->assertJsonCount(0, 'data');
    customerRequest('GET', "account/addresses/{$theirs}", as: $this->customer)->assertNotFound();
    customerRequest('PUT', "account/addresses/{$theirs}", lagosAddress(), $this->customer)->assertNotFound();
    customerRequest('DELETE', "account/addresses/{$theirs}", as: $this->customer)->assertNotFound();

    expect($this->store->run(static fn (): int => CustomerAddress::query()->where('public_id', $theirs)->count()))->toBe(1);
});

it('stops at the most addresses a customer may save', function (): void {
    config(['customers.max_addresses' => 2]);
    $token = customerTokenFor($this->customer);

    customerRequest('POST', 'account/addresses', lagosAddress(), $token)->assertCreated();
    customerRequest('POST', 'account/addresses', lagosAddress(), $token)->assertCreated();
    customerRequest('POST', 'account/addresses', lagosAddress(), $token)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'customer_address_limit_reached');

    expect($this->store->run(fn (): int => $this->customer->addresses()->count()))->toBe(2);
});

it('gives an order a copy of the address that later edits don\'t change', function (): void {
    $address = $this->store->run(fn (): CustomerAddress => CustomerAddress::factory()->for($this->customer)->create());
    $snapshot = $address->snapshot();

    customerRequest('PUT', "account/addresses/{$address->public_id}", tokyoAddress(), $this->customer)->assertOk();

    expect($snapshot->line1)->toBe('12 Admiralty Way')
        ->and($snapshot->toTaxAddress())->toEqual(new TaxAddress('NG', 'LA', '106104'));
});

it('keeps a phone that isn\'t in E.164 out of the table', function (): void {
    expect(fn () => $this->store->run(fn () => CustomerAddress::factory()->for($this->customer)->create(['phone' => '08031234567'])))
        ->toThrow(QueryException::class, 'customer_addresses_phone_e164_check');
});

it('keeps the address routes to the customer guard', function (): void {
    $staffToken = $this->store->run(static fn (): string => app(App\Shared\Auth\AccessTokenIssuer::class)->issue(StaffMember::factory()->create(), StaffMember::GUARD, 'test')->plainTextToken);

    customerRequest('GET', 'account/addresses', as: $staffToken)->assertUnauthorized();
    customerRequest('POST', 'account/addresses', lagosAddress(), $staffToken)->assertUnauthorized();
    customerRequest('GET', 'account/addresses')->assertUnauthorized();
});

it('never shows a customer of one store their addresses at another', function (): void {
    createStore('second-store');
    $token = customerTokenFor($this->customer);
    $id = customerRequest('POST', 'account/addresses', lagosAddress(), $token)->json('data.id');

    customerRequest('GET', "account/addresses/{$id}", as: $token, subdomain: 'second-store')->assertUnauthorized();
    expect($this->store->run(static fn (): int => Customer::query()->count()))->toBe(1);
});
