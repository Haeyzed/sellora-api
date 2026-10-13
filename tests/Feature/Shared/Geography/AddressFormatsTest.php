<?php

declare(strict_types=1);

use App\Shared\Geography\AddressFormats;
use App\Shared\Geography\Rules\PostalCode;
use App\Shared\Geography\StateListDifference;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 3.3: which address fields a country requires and what its
 * postcodes look like come from commerceguys/addressing, through
 * App\Shared\Geography only; its states come from nnjeim/world. Where the
 * two disagree about a country's states, the state is never required and
 * the country is listed, so nobody chooses between them silently.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    seedAddressFormatCountries();
});

it('requires the fields a country\'s addresses need, and a state only where both sources list the same states', function (): void {
    seedStatesMatchingAddressData('JP');
    $formats = app(AddressFormats::class);

    // Japanese addresses need a prefecture and a postcode, but no city.
    expect((array) $formats->for('jp'))->toMatchArray(['countryCode' => 'JP', 'requiresCity' => false, 'requiresState' => true, 'requiresPostalCode' => true])
        // Two Nigerian states in the states list, 37 in the address data.
        ->and((array) $formats->for('NG'))->toMatchArray(['requiresCity' => true, 'requiresState' => false, 'requiresPostalCode' => false])
        ->and((array) $formats->for('GB'))->toMatchArray(['requiresCity' => true, 'requiresState' => false, 'requiresPostalCode' => true])
        // The address data requires an emirate, but the states list has none to choose from.
        ->and((array) $formats->for('AE'))->toMatchArray(['requiresState' => false, 'requiresPostalCode' => false, 'postalCodePattern' => null])
        ->and($formats->for('XX'))->toBeNull()
        // Switched off in the reference data.
        ->and($formats->for('FR'))->toBeNull();
});

it('checks postcodes against the country\'s format, and accepts any short text where there is none', function (string $country, string $postalCode, bool $valid): void {
    $validator = Validator::make(['postal_code' => $postalCode], ['postal_code' => [new PostalCode($country)]]);

    expect($validator->passes())->toBe($valid);
})->with([
    'a UK postcode' => ['GB', 'SW1A 1AA', true],
    'a UK postcode in lower case' => ['GB', 'sw1a 1aa', true],
    'not a UK postcode' => ['GB', '12345', false],
    'a US ZIP+4 code' => ['US', '94043-1351', true],
    'not a US ZIP code' => ['US', '9404', false],
    'a Japanese postcode' => ['JP', '154-0023', true],
    'a Nigerian postcode' => ['NG', '106104', true],
    'not a Nigerian postcode' => ['NG', '10610', false],
    'a Nigerian postcode with more around it' => ['NG', '1061045', false],
    'a US ZIP code with more after it' => ['US', '94043x', false],
    'a Bhutanese postcode' => ['BT', '11001', true],
    'a country without a format' => ['AE', 'anything 12', true],
    'too long anywhere' => ['AE', str_repeat('9', 21), false],
]);

it('lists the countries whose sources disagree about their states, and agrees once they match', function (): void {
    $differences = collect(app(AddressFormats::class)->stateListDifferences())->keyBy('countryCode');

    // The seeded states list has a few states, or none, for each of these.
    expect($differences->keys()->sort()->values()->all())->toBe(['AE', 'GB', 'JP', 'NG', 'US'])
        ->and($differences['AE']->addressDataRequiresState)->toBeTrue()
        ->and($differences['GB']->addressDataRequiresState)->toBeFalse()
        ->and($differences['NG'])->toBeInstanceOf(StateListDifference::class)
        ->and($differences['NG']->onlyInStatesList)->toBe([])
        ->and($differences['NG']->onlyInAddressData)->toContain('KN', 'RI')->not->toContain('LA', 'FC')
        ->and($differences['US']->addressDataRequiresState)->toBeTrue();

    seedStatesMatchingAddressData('JP');
    $this->app->forgetScopedInstances();

    expect(collect(app(AddressFormats::class)->stateListDifferences())->pluck('countryCode')->all())->not->toContain('JP');

    // A state only the states list knows is a difference too.
    $japanId = (int) Illuminate\Support\Facades\DB::table('countries')->where('iso2', 'JP')->value('id');
    Illuminate\Support\Facades\DB::table('states')->insert(worldState($japanId, 'JP', 'ZZ', 'Nowhere'));
    Illuminate\Support\Facades\Cache::flush();
    $this->app->forgetScopedInstances();

    $japan = collect(app(AddressFormats::class)->stateListDifferences())->firstWhere('countryCode', 'JP');
    expect($japan?->onlyInStatesList)->toBe(['ZZ'])
        ->and($japan?->onlyInAddressData)->toBe([])
        ->and(app(AddressFormats::class)->for('JP')?->requiresState)->toBeFalse();
});

it('prints the differences for whoever decides between the sources', function (): void {
    $this->artisan('geography:state-differences')
        ->expectsOutputToContain('NG')
        ->expectsOutputToContain('US')
        ->expectsOutputToContain('5 countries differ')
        ->assertSuccessful();
});
