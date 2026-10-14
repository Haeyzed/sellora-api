<?php

declare(strict_types=1);

use App\Shared\Geography\AddressFormats;
use App\Shared\Geography\Rules\PostalCode;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 3.3: which address fields a country requires, what its postcodes
 * look like and its states all come from commerceguys/addressing, through
 * App\Shared\Geography only. The states it lists are the codes stores save,
 * so a package update that changes one is caught before it reaches them.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    seedAddressFormatCountries();
});

it('requires the fields a country\'s addresses need, the state wherever the address data requires one', function (): void {
    $formats = app(AddressFormats::class);

    // Japanese addresses need a prefecture and a postcode, but no city.
    expect((array) $formats->for('jp'))->toMatchArray(['countryCode' => 'JP', 'requiresCity' => false, 'requiresState' => true, 'requiresPostalCode' => true])
        ->and((array) $formats->for('US'))->toMatchArray(['requiresCity' => true, 'requiresState' => true, 'requiresPostalCode' => true])
        ->and((array) $formats->for('NG'))->toMatchArray(['requiresCity' => true, 'requiresState' => false, 'requiresPostalCode' => false])
        ->and((array) $formats->for('GB'))->toMatchArray(['requiresCity' => true, 'requiresState' => false, 'requiresPostalCode' => true])
        ->and((array) $formats->for('AE'))->toMatchArray(['requiresState' => true, 'requiresPostalCode' => false, 'postalCodePattern' => null])
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

it('has recorded every subdivision code the installed address data has, so none changes under saved addresses and tax rates', function (): void {
    $this->artisan('geography:subdivision-changes')->expectsOutputToContain('No subdivision codes changed')->assertSuccessful();
});

it('lists the codes an update of the address data removed or added, and fails until they are recorded', function (): void {
    $recorded = json_decode((string) file_get_contents(config()->string('geography.subdivision_codes_path')), true);
    // As if the package had renamed Telangana from "TG" to "TS" and added Cape Verde's "Boa Vista".
    $recorded['IN'] = [...array_values(array_diff($recorded['IN'], ['TS'])), 'TG'];
    $recorded['CV'] = array_values(array_diff($recorded['CV'], ['Boa Vista']));
    $path = tempnam(sys_get_temp_dir(), 'subdivisions');
    file_put_contents($path, json_encode($recorded));
    config(['geography.subdivision_codes_path' => $path]);

    $this->artisan('geography:subdivision-changes')
        ->expectsTable(['Country', 'Removed (saved rows need migrating)', 'Added'], [['CV', '-', 'Boa Vista'], ['IN', 'TG', 'TS']])
        ->expectsOutputToContain('2 countries changed')
        ->assertFailed();

    $this->artisan('geography:subdivision-changes --record')->assertSuccessful();
    $this->artisan('geography:subdivision-changes')->expectsOutputToContain('No subdivision codes changed')->assertSuccessful();

    unlink($path);
});
