<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Data\TaxCustomer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Tax-exempt customers (Step 10 decisions): customers enter their own tax
 * ID, checked for format only; only staff with customers.tax_exemption make
 * them exempt, and only with a tax ID to rest it on. Changing the ID ends
 * the exemption until staff check again. Tax receives it as TaxCustomer.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    seedAddressFormatCountries();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    $this->customer = firstStoreCustomer();

    [$this->owner, $this->manager] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        $support = Role::findOrCreate('Support', StaffMember::GUARD);
        $support->givePermissionTo(Permission::findByName('customers.manage', StaffMember::GUARD));
        $manager = StaffMember::factory()->create();
        $manager->assignRole($support);

        return [$owner, $manager];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('stores a customer\'s tax ID without spaces, dots or dashes', function (): void {
    customerRequest('PUT', 'account/tax-id', ['tax_id' => 'gb 123.456-789', 'country' => 'gb'], $this->customer)
        ->assertOk()
        ->assertJsonPath('data.tax_id', 'GB123456789')
        ->assertJsonPath('data.tax_id_country', 'GB')
        ->assertJsonPath('data.is_tax_exempt', false);
});

it('checks only a tax ID\'s format', function (array $input, string $field): void {
    customerRequest('PUT', 'account/tax-id', $input, $this->customer)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'too short' => [['tax_id' => '12 3', 'country' => 'NG'], 'tax_id'],
    'too long' => [['tax_id' => str_repeat('1', 21), 'country' => 'NG'], 'tax_id'],
    'other characters' => [['tax_id' => '1234#5678', 'country' => 'NG'], 'tax_id'],
    'no country' => [['tax_id' => '12345678'], 'country'],
    'a country we don\'t support' => [['tax_id' => '12345678', 'country' => 'XX'], 'country'],
]);

it('keeps a tax ID and its country together in the table', function (): void {
    expect(fn () => $this->store->run(fn () => DB::table('customers')->where('id', $this->customer->id)->update(['tax_id' => 'NG12345678'])))
        ->toThrow(QueryException::class, 'customers_tax_id_with_country_check');
});

it('never lets customers make themselves tax-exempt', function (): void {
    customerRequest('PATCH', 'account', ['is_tax_exempt' => true], $this->customer)->assertOk()->assertJsonPath('data.is_tax_exempt', false);
    customerRequest('PUT', 'account/tax-id', ['tax_id' => '12345678', 'country' => 'NG', 'is_tax_exempt' => true], $this->customer)->assertJsonPath('data.is_tax_exempt', false);
});

it('lets only staff with the tax exemption permission make a customer exempt, once they have a tax ID', function (): void {
    staffCustomerRequest('PUT', "/{$this->customer->public_id}/tax-exemption", ['is_tax_exempt' => true], $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'customer_tax_id_missing');

    customerRequest('PUT', 'account/tax-id', ['tax_id' => '12345678-0001', 'country' => 'NG'], $this->customer)->assertOk();

    staffCustomerRequest('PUT', "/{$this->customer->public_id}/tax-exemption", ['is_tax_exempt' => true], $this->manager)->assertForbidden();
    staffCustomerRequest('PUT', "/{$this->customer->public_id}/tax-exemption", ['is_tax_exempt' => true], $this->owner)
        ->assertOk()
        ->assertJsonPath('data.is_tax_exempt', true);

    expect($this->store->run(fn (): TaxCustomer => $this->customer->refresh()->taxCustomer()))->toEqual(new TaxCustomer(true, '123456780001', 'NG'))
        ->and($this->store->run(static fn (): array => Activity::query()->inLog('customers')->pluck('event')->all()))->toBe(['customer_tax_exemption_granted'])
        ->and($this->store->run(fn (): array => Audit::query()->where('auditable_type', 'customer')->where('auditable_id', $this->customer->id)->pluck('new_values')->all()))->toBe([['is_tax_exempt' => true]]);
});

it('ends an exemption when the customer changes or removes their tax ID', function (Closure $change): void {
    customerRequest('PUT', 'account/tax-id', ['tax_id' => '12345678', 'country' => 'NG'], $this->customer)->assertOk();
    staffCustomerRequest('PUT', "/{$this->customer->public_id}/tax-exemption", ['is_tax_exempt' => true], $this->owner)->assertOk();

    $change($this->customer);

    expect($this->store->run(fn (): bool => $this->customer->refresh()->is_tax_exempt))->toBeFalse();
})->with([
    'changed' => [fn (Customer $customer) => customerRequest('PUT', 'account/tax-id', ['tax_id' => '87654321', 'country' => 'NG'], $customer)->assertOk()->assertJsonPath('data.is_tax_exempt', false)],
    'removed' => [fn (Customer $customer) => customerRequest('DELETE', 'account/tax-id', as: $customer)->assertOk()->assertJsonPath('data.tax_id', null)],
]);

it('keeps the exemption when the same tax ID is entered again', function (): void {
    customerRequest('PUT', 'account/tax-id', ['tax_id' => '12345678', 'country' => 'NG'], $this->customer)->assertOk();
    staffCustomerRequest('PUT', "/{$this->customer->public_id}/tax-exemption", ['is_tax_exempt' => true], $this->owner)->assertOk();

    customerRequest('PUT', 'account/tax-id', ['tax_id' => '1234-5678', 'country' => 'ng'], $this->customer)->assertOk()->assertJsonPath('data.is_tax_exempt', true);
});
