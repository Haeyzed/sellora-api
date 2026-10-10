<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Money\Contracts\PricedRecords;
use App\Shared\Money\PricedRecordsRegistry;
use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Exceptions\MissingStoreSettingsException;
use App\Tenant\Settings\Jobs\SyncStoreProfile;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;

/*
 * Sections 3.3, 9.1 and 10: a store's core settings live in one typed,
 * audited row. Its first settings come from what it was registered with and
 * its country; changing the country later changes nothing else. The base
 * currency and tax mode lock once anything is priced. Name, country,
 * currency, timezone and language changes reach the platform through a
 * retried job, after the store has committed them.
 */
uses(DatabaseTruncation::class);

/**
 * Stands in for a domain that holds prices, such as Catalog once it exists.
 */
final class AlwaysPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    seedWorld();

    $this->store = createStore('settings-store', ['name' => 'Ada Fabrics']);

    [$this->owner, $this->viewer, $this->outsider] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $viewerRole = Role::findOrCreate('Settings viewer', StaffMember::GUARD);
        $viewerRole->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate('settings.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($viewerRole);

        return [$owner, $viewer, StaffMember::factory()->create()];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * Sends a request to the store's settings API as the given staff member.
 *
 * @param  array<string, mixed>  $data
 */
function storeSettingsRequest(string $method, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('settings-store', '/api/v1/staff/store/settings'), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function currentStoreSettings(): StoreSettings
{
    return test()->store->run(static fn (): StoreSettings => app(FindStoreSettings::class)->handle());
}

it('starts a store with what it was registered with and its country\'s tax mode and units', function (): void {
    $response = storeSettingsRequest('GET')->assertOk();
    $updatedAt = $this->store->run(static fn (): ?string => StoreSettings::query()->sole()->updated_at?->toIso8601String());

    $response->assertExactJson(['data' => [
        'name' => 'Ada Fabrics', 'logo' => null, 'country' => 'NG', 'currency' => 'NGN', 'timezone' => 'Africa/Lagos',
        'default_locale' => 'en', 'enabled_locales' => ['en'], 'tax_mode' => 'inclusive', 'pricing_locked' => false, 'require_staff_two_factor' => false,
        'weight_unit' => 'kg', 'dimension_unit' => 'cm', 'low_stock_threshold' => 5, 'contact_email' => null, 'contact_phone' => null, 'address' => null,
        'updated_at' => $updatedAt,
    ]]);
});

it('starts a store in the United States tax-exclusive, in pounds and inches', function (): void {
    // Set the store up again as if it had registered in the United States.
    $this->store->update(['country_code' => 'US', 'currency_code' => 'USD', 'timezone' => 'America/Chicago']);
    $this->store->run(static function (): void {
        DB::table('store_settings')->delete();
        app(StoreSettingsSetup::class)->initialize();
    });

    storeSettingsRequest('GET')->assertOk()
        ->assertJsonPath('data.tax_mode', 'exclusive')
        ->assertJsonPath('data.weight_unit', 'lb')
        ->assertJsonPath('data.dimension_unit', 'in');
});

it('lets staff see the settings with settings.view, and change them only with settings.manage', function (): void {
    storeSettingsRequest('GET', as: $this->viewer)->assertOk();
    storeSettingsRequest('PATCH', ['name' => 'Taken over'], $this->viewer)->assertForbidden();
    storeSettingsRequest('GET', as: $this->outsider)->assertForbidden();

    expect(currentStoreSettings()->name)->toBe('Ada Fabrics');
});

it('changes settings, stores the phone in E.164 and codes in upper case, and audits each change', function (): void {
    storeSettingsRequest('PATCH', [
        'name' => '  Ada Fabrics & Co ',
        'contact_email' => 'Hello@AdaFabrics.example',
        'contact_phone' => '0803 123 4567',
        'enabled_locales' => ['en', 'fr'],
        'weight_unit' => 'g',
        'address' => ['line1' => '12 Marina', 'city' => 'Lagos', 'state' => 'la', 'postal_code' => '101001', 'country' => 'ng'],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Ada Fabrics & Co')
        ->assertJsonPath('data.contact_email', 'hello@adafabrics.example')
        ->assertJsonPath('data.contact_phone', '+2348031234567')
        ->assertJsonPath('data.enabled_locales', ['en', 'fr'])
        ->assertJsonPath('data.address', ['line1' => '12 Marina', 'line2' => null, 'city' => 'Lagos', 'state' => 'LA', 'postal_code' => '101001', 'country' => 'NG']);

    $audit = $this->store->run(static fn (): Audit => Audit::query()->where('auditable_type', 'store_settings')->where('event', 'updated')->sole());
    expect($audit->old_values)->toMatchArray(['name' => 'Ada Fabrics', 'weight_unit' => 'kg'])
        ->and($audit->new_values)->toMatchArray(['name' => 'Ada Fabrics & Co', 'contact_phone' => '+2348031234567']);

    storeSettingsRequest('PATCH', ['address' => null])->assertOk()->assertJsonPath('data.address', null);
});

it('refuses codes that aren\'t ISO, unknown languages and phone numbers, and a default language that isn\'t enabled', function (): void {
    storeSettingsRequest('PATCH', [
        'country' => 'FR',
        'currency' => 'DEM',
        'timezone' => 'US/Eastern',
        'contact_phone' => '12345',
        'tax_mode' => 'sometimes',
        'address' => ['state' => 'CA', 'country' => 'NG'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['country', 'currency', 'timezone', 'contact_phone', 'tax_mode', 'address.state']);

    storeSettingsRequest('PATCH', ['default_locale' => 'xx'])->assertUnprocessable()->assertJsonValidationErrors('default_locale');
    storeSettingsRequest('PATCH', ['default_locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('default_locale');
    storeSettingsRequest('PATCH', ['enabled_locales' => ['fr']])->assertUnprocessable()->assertJsonValidationErrors('default_locale');
    storeSettingsRequest('PATCH', ['address' => ['city' => 'Lagos']])->assertUnprocessable()->assertJsonValidationErrors('address.country');

    storeSettingsRequest('PATCH', ['default_locale' => 'fr', 'enabled_locales' => ['fr', 'en']])->assertOk()->assertJsonPath('data.default_locale', 'fr');
});

it('changes nothing else when the country changes: country defaults apply only at sign-up', function (): void {
    storeSettingsRequest('PATCH', ['country' => 'us'])->assertOk()
        ->assertJsonPath('data.country', 'US')
        ->assertJsonPath('data.currency', 'NGN')
        ->assertJsonPath('data.timezone', 'Africa/Lagos')
        ->assertJsonPath('data.tax_mode', 'inclusive')
        ->assertJsonPath('data.weight_unit', 'kg');
});

it('locks the base currency and tax mode once anything in the store is priced', function (): void {
    storeSettingsRequest('PATCH', ['currency' => 'usd', 'tax_mode' => 'exclusive'])->assertOk()->assertJsonPath('data.currency', 'USD');

    app(PricedRecordsRegistry::class)->register(AlwaysPricedRecords::class);

    storeSettingsRequest('GET')->assertOk()->assertJsonPath('data.pricing_locked', true);
    storeSettingsRequest('PATCH', ['currency' => 'NGN'])->assertConflict()->assertJsonPath('code', 'pricing_settings_locked');
    storeSettingsRequest('PATCH', ['tax_mode' => 'inclusive'])->assertConflict()->assertJsonPath('code', 'pricing_settings_locked');

    storeSettingsRequest('PATCH', ['currency' => 'USD', 'tax_mode' => 'exclusive', 'name' => 'Still allowed'])->assertOk();
    expect(currentStoreSettings())->currency_code->toBe('USD')->name->toBe('Still allowed');
});

it('sends name, country, currency, timezone and language changes to the platform after the store commits, safe to run twice', function (): void {
    Queue::fake();

    storeSettingsRequest('PATCH', ['weight_unit' => 'lb', 'contact_email' => 'shop@example.com'])->assertOk();
    Queue::assertNotPushed(SyncStoreProfile::class);

    storeSettingsRequest('PATCH', [
        'name' => 'Ada Textiles', 'country' => 'DE', 'currency' => 'EUR', 'timezone' => 'Europe/Berlin',
        'default_locale' => 'de', 'enabled_locales' => ['de', 'en'],
    ])->assertOk();

    $job = Queue::pushed(SyncStoreProfile::class)->sole();
    expect($this->store->refresh()->name)->toBe('Ada Fabrics')
        ->and($job->retryUntil())->toBeGreaterThanOrEqual(now()->addDays(6));

    $this->store->run(static fn () => app()->call([$job, 'handle']));
    $this->store->run(static fn () => app()->call([$job, 'handle']));

    expect($this->store->refresh())
        ->name->toBe('Ada Textiles')
        ->country_code->toBe('DE')
        ->currency_code->toBe('EUR')
        ->timezone->toBe('Europe/Berlin')
        ->locale->toBe('de');
});

it('never updates the platform\'s copy of a store being purged', function (): void {
    Queue::fake();
    storeSettingsRequest('PATCH', ['name' => 'Too late'])->assertOk();
    $this->store->update(['status' => TenantStatus::Purging]);

    $this->store->run(static fn () => app()->call([Queue::pushed(SyncStoreProfile::class)->sole(), 'handle']));

    expect(Tenant::query()->findOrFail($this->store->id)->name)->toBe('Ada Fabrics');
});

it('keeps exactly one settings row, with its default language enabled, in the database too', function (): void {
    currentStoreSettings();

    $this->store->run(static function (): void {
        $row = (array) DB::table('store_settings')->first();

        expect(static fn () => DB::transaction(static fn () => DB::table('store_settings')->insert([...$row, 'id' => 2])))
            ->toThrow(QueryException::class, 'store_settings_single_row')
            ->and(static fn () => DB::transaction(static fn () => DB::table('store_settings')->update(['default_locale' => 'fr'])))
            ->toThrow(QueryException::class, 'store_settings_default_locale_enabled')
            ->and(static fn () => DB::transaction(static fn () => DB::table('store_settings')->update(['address_state_code' => 'LA'])))
            ->toThrow(QueryException::class, 'store_settings_state_needs_country');
    });
});

it('fails loudly when a store has no settings, instead of creating them on read', function (): void {
    $this->store->run(static fn () => DB::table('store_settings')->delete());

    storeSettingsRequest('GET')->assertServerError();

    expect($this->store->run(static fn (): int => DB::table('store_settings')->count()))->toBe(0)
        ->and(fn () => $this->store->run(static fn () => app(FindStoreSettings::class)->handle()))->toThrow(MissingStoreSettingsException::class);
});

it('lets staff set the store\'s low-stock threshold, a whole number from 0', function (): void {
    storeSettingsRequest('PATCH', ['low_stock_threshold' => 3])->assertOk()->assertJsonPath('data.low_stock_threshold', 3);
    storeSettingsRequest('PATCH', ['low_stock_threshold' => -1])->assertUnprocessable()->assertJsonValidationErrors('low_stock_threshold');
    storeSettingsRequest('PATCH', ['low_stock_threshold' => '4'])->assertUnprocessable()->assertJsonValidationErrors('low_stock_threshold');
});
