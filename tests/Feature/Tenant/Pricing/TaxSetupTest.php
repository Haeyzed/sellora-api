<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;

/*
 * Section 3.3: merchants set up their own tax classes and rates; none are
 * seeded. Exactly one class is the default, at most one is used for
 * shipping, and a class still in use can't be deleted. One rate per class
 * and region, where an empty state is one value. Everything is audited.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('tax-setup-store');

    [$this->owner, $this->viewer, $this->variant] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $role = Role::findOrCreate('Accountant', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('pricing.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        $product = Product::factory()->create(['name' => ['en' => 'Linen shirt'], 'slug' => 'linen-shirt']);

        return [$owner, $viewer, ProductVariant::factory()->create(['product_id' => $product->id, 'price' => Money::ofMinor(1999, 'NGN'), 'currency' => 'NGN'])];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @param  array<string, mixed>  $data
 */
function pricingRequest(string $method, string $path, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('tax-setup-store', '/api/v1/staff/pricing'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function taxClassId(string $name): string
{
    return test()->store->run(static fn (): string => TaxClass::query()->where('name', $name)->sole()->public_id);
}

it('starts every store with four classes and no rates', function (): void {
    $classes = pricingRequest('GET', '/tax-classes')->assertOk()->assertJsonPath('meta.total', 4)->json('data');

    expect(collect($classes)->mapWithKeys(static fn (array $class): array => [$class['name'] => [$class['is_exempt'], $class['is_default'], $class['used_for_shipping']]])->all())->toBe([
        'Exempt' => [true, false, false],
        'Reduced' => [false, false, false],
        'Standard' => [false, true, true],
        'Zero-rated' => [false, false, false],
    ]);

    pricingRequest('GET', '/tax-rates')->assertOk()->assertJsonPath('meta.total', 0);
});

it('adds, renames and moves the default and shipping between classes, always keeping exactly one default', function (): void {
    $kids = pricingRequest('POST', '/tax-classes', ['name' => "Children's clothing"])->assertCreated()->assertJsonPath('data.is_default', false)->json('data.id');
    pricingRequest('POST', '/tax-classes', ['name' => "CHILDREN'S CLOTHING"])->assertUnprocessable()->assertJsonPath('code', 'tax_class_name_taken');

    pricingRequest('PATCH', "/tax-classes/{$kids}", ['is_default' => true, 'used_for_shipping' => true])->assertOk()->assertJsonPath('data.is_default', true);
    pricingRequest('GET', '/tax-classes/'.taxClassId('Standard'))->assertJsonPath('data.is_default', false)->assertJsonPath('data.used_for_shipping', false);

    // A default stops being one only when another becomes it.
    pricingRequest('PATCH', "/tax-classes/{$kids}", ['is_default' => false])->assertUnprocessable()->assertJsonValidationErrors('is_default');

    pricingRequest('PATCH', "/tax-classes/{$kids}", ['used_for_shipping' => false, 'name' => 'Kids'])->assertOk()->assertJsonPath('data.name', 'Kids');
    expect($this->store->run(static fn (): array => [TaxClass::query()->where('is_default', true)->count(), TaxClass::query()->where('used_for_shipping', true)->count()]))->toBe([1, 0]);
});

it('refuses to delete a class that is still used, and deletes one that isn\'t', function (): void {
    $kids = pricingRequest('POST', '/tax-classes', ['name' => 'Kids'])->json('data.id');

    // Shipping moves to Kids first, so Standard is only the default.
    pricingRequest('PATCH', "/tax-classes/{$kids}", ['used_for_shipping' => true]);
    pricingRequest('DELETE', '/tax-classes/'.taxClassId('Standard'))->assertConflict()->assertJsonPath('code', 'tax_class_in_use');
    pricingRequest('DELETE', "/tax-classes/{$kids}")->assertConflict()->assertJsonPath('code', 'tax_class_in_use');
    pricingRequest('PATCH', '/tax-classes/'.taxClassId('Standard'), ['used_for_shipping' => true]);

    $rate = pricingRequest('POST', '/tax-rates', ['tax_class' => $kids, 'country_code' => 'NG', 'name' => 'VAT', 'rate' => '0'])->json('data.id');
    pricingRequest('DELETE', "/tax-classes/{$kids}")->assertConflict()->assertJsonPath('code', 'tax_class_in_use');
    pricingRequest('DELETE', "/tax-rates/{$rate}")->assertNoContent();

    // A variant in the trash still uses it.
    pricingRequest('PATCH', "/variants/{$this->variant->public_id}", ['tax_class' => $kids])->assertOk();
    $this->store->run(fn () => ProductVariant::query()->whereKey($this->variant->id)->delete());
    pricingRequest('DELETE', "/tax-classes/{$kids}")->assertConflict()->assertJsonPath('code', 'tax_class_in_use');

    pricingRequest('PATCH', "/variants/{$this->variant->public_id}", ['tax_class' => null])->assertOk()->assertJsonPath('data.uses_default_tax_class', true);
    pricingRequest('DELETE', "/tax-classes/{$kids}")->assertNoContent();
    pricingRequest('GET', "/tax-classes/{$kids}")->assertNotFound();
});

it('takes a rate as a percentage string from 0 to 100 with up to 4 decimals, for a real country and one of its states', function (): void {
    $standard = taxClassId('Standard');

    pricingRequest('POST', '/tax-rates', ['tax_class' => $standard, 'country_code' => 'NG', 'state_code' => 'LA', 'name' => 'VAT', 'rate' => '7.5'])
        ->assertCreated()
        ->assertJsonPath('data.rate', '7.5000')
        ->assertJsonPath('data.state_code', 'LA')
        ->assertJsonPath('data.tax_class.name', 'Standard');

    foreach ([7.5, '-1', '100.0001', '7.55555', 'abc', '1e2', '1000'] as $invalid) {
        pricingRequest('POST', '/tax-rates', ['tax_class' => $standard, 'country_code' => 'NG', 'name' => 'VAT', 'rate' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('rate');
    }

    pricingRequest('POST', '/tax-rates', ['tax_class' => $standard, 'country_code' => 'NG', 'name' => 'VAT', 'rate' => '100'])->assertCreated();
    pricingRequest('POST', '/tax-rates', ['tax_class' => $standard, 'country_code' => 'NG', 'state_code' => 'CA', 'name' => 'VAT', 'rate' => '5'])->assertJsonValidationErrors('state_code');
    pricingRequest('POST', '/tax-rates', ['tax_class' => $standard, 'country_code' => 'XX', 'name' => 'VAT', 'rate' => '5'])->assertJsonValidationErrors('country_code');
    pricingRequest('POST', '/tax-rates', ['tax_class' => taxClassId('Exempt'), 'country_code' => 'NG', 'name' => 'VAT', 'rate' => '5'])->assertJsonValidationErrors('tax_class');
});

it('allows one rate per class and region, counting the country-wide rate as one region', function (): void {
    $standard = taxClassId('Standard');
    $rate = ['tax_class' => $standard, 'country_code' => 'US', 'name' => 'Sales tax', 'rate' => '5'];

    pricingRequest('POST', '/tax-rates', $rate)->assertCreated();
    pricingRequest('POST', '/tax-rates', $rate)->assertConflict()->assertJsonPath('code', 'tax_rate_exists');
    pricingRequest('POST', '/tax-rates', [...$rate, 'state_code' => null])->assertConflict();
    pricingRequest('POST', '/tax-rates', [...$rate, 'state_code' => 'CA'])->assertCreated();
    pricingRequest('POST', '/tax-rates', [...$rate, 'state_code' => 'CA'])->assertConflict();
    pricingRequest('POST', '/tax-rates', [...$rate, 'tax_class' => taxClassId('Reduced')])->assertCreated();

    // The database itself refuses a second country-wide rate, however it is written.
    $this->store->run(static function (): void {
        $insert = static fn (): bool => DB::table('tax_rates')->insert([
            'public_id' => strtolower((string) Str::ulid()), 'tax_class_id' => TaxClass::query()->where('name', 'Zero-rated')->value('id'),
            'country_code' => 'DE', 'state_code' => null, 'name' => 'MwSt', 'rate' => '0', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $insert();
        expect($insert)->toThrow(QueryException::class);
    });

    pricingRequest('GET', '/tax-rates?country=US')->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.state_code', null);
    pricingRequest('GET', '/tax-rates?tax_class='.taxClassId('Reduced'))->assertJsonPath('meta.total', 1);
});

it('changes a rate\'s name and percentage, and audits classes, rates and variants\' classes', function (): void {
    $rate = pricingRequest('POST', '/tax-rates', ['tax_class' => taxClassId('Standard'), 'country_code' => 'NG', 'name' => 'VAT', 'rate' => '5'])->json('data.id');
    pricingRequest('PATCH', "/tax-rates/{$rate}", ['rate' => '7.5', 'name' => 'Value added tax'])->assertOk()->assertJsonPath('data.rate', '7.5000');

    pricingRequest('PATCH', "/variants/{$this->variant->public_id}", ['tax_class' => taxClassId('Reduced')])
        ->assertOk()
        ->assertJsonPath('data.tax_class.name', 'Reduced')
        ->assertJsonPath('data.uses_default_tax_class', false);

    $this->store->run(static function (): void {
        $rateAudit = Audit::query()->where('auditable_type', 'tax_rate')->latest('id')->first();
        expect($rateAudit?->old_values)->toBe(['name' => 'VAT', 'rate' => '5.0000'])
            ->and($rateAudit?->new_values)->toBe(['name' => 'Value added tax', 'rate' => '7.5000'])
            ->and(Audit::query()->where('auditable_type', 'variant_tax_class')->count())->toBe(1);
    });

    pricingRequest('POST', '/tax-classes', ['name' => 'Kids']);
    expect($this->store->run(static fn (): int => Audit::query()->where('auditable_type', 'tax_class')->where('event', 'created')->count()))->toBe(1);
});

it('puts every variant of a product in a class, those in the trash too, and back in the default', function (): void {
    $product = $this->store->run(fn (): Product => $this->variant->product);
    $trashed = $this->store->run(static function () use ($product): ProductVariant {
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'GONE', 'attribute_signature' => 'gone']);
        $variant->delete();

        return $variant;
    });

    pricingRequest('PUT', "/products/{$product->public_id}/tax-class", ['tax_class' => taxClassId('Zero-rated')])->assertNoContent();
    pricingRequest('GET', "/variants/{$trashed->public_id}")->assertOk()->assertJsonPath('data.tax_class.name', 'Zero-rated');
    pricingRequest('GET', "/variants/{$this->variant->public_id}")->assertJsonPath('data.tax_class.name', 'Zero-rated');

    pricingRequest('PUT', "/products/{$product->public_id}/tax-class", ['tax_class' => null])->assertNoContent();
    pricingRequest('GET', "/variants/{$trashed->public_id}")->assertJsonPath('data.tax_class.name', 'Standard')->assertJsonPath('data.uses_default_tax_class', true);
    pricingRequest('PUT', "/products/{$product->public_id}/tax-class", [])->assertJsonValidationErrors('tax_class');
});

it('lets pricing.view look but not change, and refuses staff without it', function (): void {
    $standard = taxClassId('Standard');

    pricingRequest('GET', '/tax-classes', as: $this->viewer)->assertOk();
    pricingRequest('GET', "/variants/{$this->variant->public_id}", as: $this->viewer)->assertOk();
    pricingRequest('POST', '/tax-classes', ['name' => 'Kids'], as: $this->viewer)->assertForbidden();
    pricingRequest('PATCH', "/tax-classes/{$standard}", ['name' => 'Normal'], as: $this->viewer)->assertForbidden();
    pricingRequest('POST', '/tax-rates', ['tax_class' => $standard, 'country_code' => 'NG', 'name' => 'VAT', 'rate' => '5'], as: $this->viewer)->assertForbidden();
    pricingRequest('PATCH', "/variants/{$this->variant->public_id}", ['tax_class' => null], as: $this->viewer)->assertForbidden();
    pricingRequest('DELETE', "/tax-classes/{$standard}", as: $this->viewer)->assertForbidden();

    $stranger = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());
    pricingRequest('GET', '/tax-classes', as: $stranger)->assertForbidden();
    pricingRequest('GET', '/tax-rates', as: $stranger)->assertForbidden();
});

it('keeps each store\'s tax setup to itself', function (): void {
    $other = createStore('other-tax-setup-store');
    pricingRequest('POST', '/tax-rates', ['tax_class' => taxClassId('Standard'), 'country_code' => 'NG', 'name' => 'VAT', 'rate' => '7.5'])->assertCreated();
    $kids = pricingRequest('POST', '/tax-classes', ['name' => 'Kids'])->json('data.id');

    $otherOwner = $other->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $token = $other->run(static fn (): string => app(AccessTokenIssuer::class)->issue($otherOwner, StaffMember::GUARD, 'test')->plainTextToken);
    forgetSignIns();

    $this->withToken($token)->getJson(storeUrl('other-tax-setup-store', '/api/v1/staff/pricing/tax-rates'))->assertOk()->assertJsonPath('meta.total', 0);
    tenancy()->end();
    forgetSignIns();
    $this->withToken($token)->getJson(storeUrl('other-tax-setup-store', "/api/v1/staff/pricing/tax-classes/{$kids}"))->assertNotFound();
    tenancy()->end();
});
