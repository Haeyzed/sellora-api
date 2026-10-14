<?php

declare(strict_types=1);

use App\Tenant\Customers\Models\CustomerAddress;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 3.3: states come from one source, so a tax rate saved for a state
 * applies to an address saved in it. Both store the same code, whatever case
 * it was sent in. Where the address data lists no states for a country, an
 * address's state is free text and only a country-wide rate can be saved.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    seedWorldCountry('IN', 'India', '91', 'INR', 'Asia/Kolkata');
    seedWorldCountry('CV', 'Cape Verde', '238', 'CVE', 'Atlantic/Cape_Verde');
    $this->store = createStore('tax-setup-store');
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $this->customer = firstStoreCustomer();
});

afterEach(function (): void {
    deleteAllStores();
});

it('applies a state\'s tax rate to an address in that state, both saved with the same code', function (array $address, string $sent, string $code, string $otherListsCode): void {
    pricingRequest('POST', '/tax-rates', ['tax_class' => taxClassId('Standard'), 'country_code' => $address['country'], 'state_code' => $sent, 'name' => 'State tax', 'rate' => '5'])
        ->assertCreated()
        ->assertJsonPath('data.state_code', $code);
    customerRequest('POST', 'account/addresses', [...$address, 'state' => $sent], $this->customer, 'tax-setup-store')
        ->assertCreated()
        ->assertJsonPath('data.state', $code);

    $tax = $this->store->run(static fn () => calculatedTaxFor(['shirt' => [10000, 'Standard']], CustomerAddress::query()->sole()->snapshot()->toTaxAddress()));

    expect($tax->line('shirt')->tax->minorAmount())->toBe(500)
        ->and($tax->line('shirt')->taxes[0]->stateCode)->toBe($code);

    // The code nnjeim/world had is refused by both.
    pricingRequest('POST', '/tax-rates', ['tax_class' => taxClassId('Reduced'), 'country_code' => $address['country'], 'state_code' => $otherListsCode, 'name' => 'State tax', 'rate' => '5'])->assertJsonValidationErrors('state_code');
    customerRequest('POST', 'account/addresses', [...$address, 'state' => $otherListsCode], $this->customer, 'tax-setup-store')->assertJsonValidationErrors('state');
})->with([
    // nnjeim/world had California's code too, but also "UM" (US Minor Outlying Islands), which the address data files as a country of its own.
    'California' => [californiaAddress(), 'ca', 'CA', 'UM'],
    // ISO 3166-2 and nnjeim/world have "TG" for Telangana; the address data, and so every saved address and rate, has "TS".
    'Telangana' => [['recipient_name' => 'Ada Lovelace', 'line1' => 'Plot 1, HITEC City', 'city' => 'Hyderabad', 'postal_code' => '500081', 'country' => 'IN'], 'ts', 'TS', 'TG'],
    // Without ISO codes in the address data, Cape Verde's islands are keyed by name (ISO 3166-2 has "CV-SV").
    'São Vicente' => [['recipient_name' => 'Ada Lovelace', 'line1' => 'Rua de Lisboa 1', 'city' => 'Mindelo', 'country' => 'CV'], 'são vicente', 'São Vicente', 'SV'],
]);

it('takes any state as text for an address where the country lists none, and only a country-wide tax rate there', function (): void {
    customerRequest('POST', 'account/addresses', berlinAddress(['state' => ' Berlin ']), $this->customer, 'tax-setup-store')
        ->assertCreated()
        ->assertJsonPath('data.state', 'Berlin');

    pricingRequest('POST', '/tax-rates', ['tax_class' => taxClassId('Standard'), 'country_code' => 'DE', 'state_code' => 'Berlin', 'name' => 'MwSt', 'rate' => '19'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state_code' => 'country-wide rate']);
    pricingRequest('POST', '/tax-rates', ['tax_class' => taxClassId('Standard'), 'country_code' => 'DE', 'name' => 'MwSt', 'rate' => '19'])
        ->assertCreated()
        ->assertJsonPath('data.state_code', null);

    $tax = $this->store->run(static fn () => calculatedTaxFor(['shirt' => [10000, 'Standard']], CustomerAddress::query()->sole()->snapshot()->toTaxAddress()));
    expect($tax->line('shirt')->tax->minorAmount())->toBe(1900);
});

it('refuses a US address without a state', function (): void {
    customerRequest('POST', 'account/addresses', californiaAddress(['state' => null]), $this->customer, 'tax-setup-store')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');
});
