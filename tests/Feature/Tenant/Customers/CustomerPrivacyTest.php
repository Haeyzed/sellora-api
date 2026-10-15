<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Customers\Actions\RecordMarketingConsent;
use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Enums\MarketingConsentSource;
use App\Tenant\Customers\Enums\MarketingConsentStatus;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use App\Tenant\Customers\Models\CustomerDataExport;
use App\Tenant\Customers\Models\CustomerNote;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Privacy\FailingPersonalDataHandler;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Personal data (section 3.3 and 10): a verified customer downloads
 * everything the store holds about them, staff notes included without their
 * author; erasure, by the customer or by staff for them, clears every
 * personal field all or nothing and deletes files only after it commits;
 * customers left in the trash are erased after 90 days, and nobody else is.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    $this->customer = firstStoreCustomer(['email_verified_at' => now(), 'phone' => '+2348031234567', 'tax_id' => '12345678', 'tax_id_country' => 'NG', 'is_tax_exempt' => true, 'locale' => 'en']);

    [$this->owner, $this->manager] = $this->store->run(function (): array {
        $owner = StaffMember::factory()->create(['password' => 'a-long-staff-password']);
        $owner->assignRole(StaffRole::Owner->value);
        $support = Role::findOrCreate('Support', StaffMember::GUARD);
        $support->givePermissionTo(Permission::findByName('customers.manage', StaffMember::GUARD));
        $manager = StaffMember::factory()->create(['password' => 'a-long-staff-password']);
        $manager->assignRole($support);

        $address = CustomerAddress::factory()->for($this->customer)->create();
        $this->customer->forceFill(['default_shipping_address_id' => $address->id])->save();
        $note = $this->customer->notes()->make(['body' => 'Asked for gift wrapping.']);
        $note->author_staff_id = $owner->id;
        $note->save();
        $consents = app(RecordMarketingConsent::class);
        $consents->handle(MarketingChannel::Email, 'ada@example.com', MarketingConsentStatus::Granted, MarketingConsentSource::Account, 'en', $this->customer);
        // A guest's answer with the same email at checkout: it is theirs too.
        $consents->handle(MarketingChannel::Sms, '+2348031234567', MarketingConsentStatus::Granted, MarketingConsentSource::Checkout, 'en');
        // Someone else's.
        $consents->handle(MarketingChannel::Email, 'grace@example.com', MarketingConsentStatus::Granted, MarketingConsentSource::Checkout, 'en');

        return [$owner, $manager];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('builds a verified customer\'s data export with every section, staff notes included without their author', function (): void {
    $id = customerRequest('POST', 'account/data-exports', as: $this->customer)->assertAccepted()->json('data.id');

    customerRequest('GET', 'account/data-exports', as: $this->customer)->assertOk()->assertJsonPath('data.0.status', 'ready')->assertJsonPath('data.0.downloadable', true);
    $file = customerRequest('GET', "account/data-exports/{$id}/download", as: $this->customer)->assertOk()->assertDownload("my-data-{$id}.json")->streamedContent();
    $data = json_decode($file, true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($data))->toBe(['exported_at', 'addresses', 'staff_notes', 'customer_groups', 'marketing_consents', 'data_exports', 'promotion_uses', 'cart', 'account'])
        ->and($data['account'][0])->toMatchArray(['email' => 'ada@example.com', 'phone' => '+2348031234567', 'tax_id' => '12345678'])
        ->and($data['addresses'][0]['line1'])->toBe('12 Admiralty Way')
        ->and($data['staff_notes'])->toBe([['id' => $data['staff_notes'][0]['id'], 'body' => 'Asked for gift wrapping.', 'created_at' => $data['staff_notes'][0]['created_at']]])
        ->and(array_column($data['marketing_consents'], 'contact'))->toBe(['ada@example.com', '+2348031234567'])
        ->and($file)->not->toContain('author')->not->toContain('grace@example.com');
});

it('refuses an export, or its download, to a customer whose email isn\'t verified', function (): void {
    $id = customerRequest('POST', 'account/data-exports', as: $this->customer)->json('data.id');
    $this->store->run(fn () => $this->customer->forceFill(['email_verified_at' => null])->save());

    customerRequest('POST', 'account/data-exports', as: $this->customer)->assertForbidden()->assertJsonPath('code', 'customer_email_not_verified');
    customerRequest('GET', "account/data-exports/{$id}/download", as: $this->customer)->assertForbidden();
});

it('lets only the customer download their export, and only until it expires', function (): void {
    $id = customerRequest('POST', 'account/data-exports', as: $this->customer)->json('data.id');

    customerRequest('GET', "account/data-exports/{$id}/download", as: firstStoreCustomer(['email' => 'mallory@example.com', 'email_verified_at' => now()]))->assertNotFound();
    $this->travel(169)->hours();
    customerRequest('GET', "account/data-exports/{$id}/download", as: $this->customer)->assertNotFound();
});

it('erases every personal field when the customer asks, keeping the row, and frees the email', function (): void {
    $token = customerTokenFor($this->customer);
    $exportPath = exportFileOf(customerRequest('POST', 'account/data-exports', as: $this->customer)->json('data.id'));

    customerRequest('DELETE', 'account', ['current_password' => 'a-long-shopper-password'], $token)->assertNoContent();

    $erased = $this->store->run(fn (): array => Customer::withTrashed()->findOrFail($this->customer->id)->only([
        'name', 'email', 'password', 'phone', 'locale', 'tax_id', 'tax_id_country', 'is_tax_exempt', 'is_active', 'search_text', 'default_shipping_address_id', 'default_billing_address_id',
    ]));
    expect($erased)->toBe([
        'name' => null, 'email' => null, 'password' => null, 'phone' => null, 'locale' => null, 'tax_id' => null, 'tax_id_country' => null,
        'is_tax_exempt' => false, 'is_active' => false, 'search_text' => '', 'default_shipping_address_id' => null, 'default_billing_address_id' => null,
    ])
        ->and($this->store->run(fn (): bool => Customer::withTrashed()->findOrFail($this->customer->id)->erased_at !== null))->toBeTrue()
        ->and(remainingPersonalRecords())->toBe(['addresses' => 0, 'notes' => 0, 'exports' => 0, 'tokens' => 0, 'consents' => ['grace@example.com']])
        ->and($this->store->run(static fn (): bool => Storage::disk('local')->exists($exportPath)))->toBeFalse();

    customerRequest('GET', 'auth/me', as: $token)->assertUnauthorized();
    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails())->assertCreated();
});

it('erases nothing without the customer\'s current password', function (): void {
    customerRequest('DELETE', 'account', ['current_password' => 'not-my-password'], $this->customer)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'current_password_incorrect');

    expect($this->store->run(fn (): ?string => $this->customer->refresh()->email))->toBe('ada@example.com');
});

it('lets staff with the privacy permission see and erase a customer\'s data, with their own password', function (): void {
    staffCustomerRequest('GET', "/{$this->customer->public_id}/personal-data", as: $this->manager)->assertForbidden();
    staffCustomerRequest('POST', "/{$this->customer->public_id}/erasure", ['password' => 'a-long-staff-password'], $this->manager)->assertForbidden();

    staffCustomerRequest('GET', "/{$this->customer->public_id}/personal-data", as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.account.0.email', 'ada@example.com')
        ->assertJsonPath('data.staff_notes.0.body', 'Asked for gift wrapping.');
    staffCustomerRequest('POST', "/{$this->customer->public_id}/erasure", ['password' => 'a-long-shopper-password'], $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'current_password_incorrect');
    staffCustomerRequest('POST', "/{$this->customer->public_id}/erasure", ['password' => 'a-long-staff-password'], $this->owner)->assertNoContent();

    staffCustomerRequest('GET', "/{$this->customer->public_id}", as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.erased_at', fn (?string $at): bool => $at !== null);
    expect($this->store->run(static fn (): array => Activity::query()->inLog('customers')->pluck('event')->all()))->toBe(['customer_erased']);
});

it('erases all or nothing, and keeps the files when an erasure fails', function (): void {
    $exportPath = exportFileOf(customerRequest('POST', 'account/data-exports', as: $this->customer)->json('data.id'));
    app(PersonalDataRegistry::class)->register('customer', 'failing_feature', FailingPersonalDataHandler::class);

    customerRequest('DELETE', 'account', ['current_password' => 'a-long-shopper-password'], $this->customer)->assertServerError();

    expect($this->store->run(fn (): ?string => $this->customer->refresh()->email))->toBe('ada@example.com')
        // Two tokens: one for each request above.
        ->and(remainingPersonalRecords())->toBe(['addresses' => 1, 'notes' => 1, 'exports' => 1, 'tokens' => 2, 'consents' => ['ada@example.com', '+2348031234567', 'grace@example.com']])
        ->and($this->store->run(static fn (): bool => Storage::disk('local')->exists($exportPath)))->toBeTrue();
});

it('ties every cleared field to erased_at in the table, with nulls in place', function (array $row, bool $allowed): void {
    $insert = fn () => $this->store->run(static fn () => DB::table('customers')->insert([
        'public_id' => (string) Str::ulid(), 'created_at' => now(), 'updated_at' => now(), 'search_text' => '', 'is_active' => false, ...$row,
    ]));

    $allowed ? expect($insert)->not->toThrow(QueryException::class) : expect($insert)->toThrow(QueryException::class, 'customers_erasure_check');
})->with([
    'erased, every field null' => [['erased_at' => now(), 'name' => null, 'email' => null, 'password' => null], true],
    'erased, a name left' => [['erased_at' => now(), 'name' => 'Ada', 'email' => null, 'password' => null], false],
    'erased, an email left' => [['erased_at' => now(), 'name' => null, 'email' => 'ada@example.com', 'password' => null], false],
    'erased, a phone left' => [['erased_at' => now(), 'name' => null, 'email' => null, 'password' => null, 'phone' => '+2348031234567'], false],
    'erased, still exempt' => [['erased_at' => now(), 'name' => null, 'email' => null, 'password' => null, 'is_tax_exempt' => true], false],
    'erased, still active' => [['erased_at' => now(), 'name' => null, 'email' => null, 'password' => null, 'is_active' => true], false],
    'erased, search text left' => [['erased_at' => now(), 'name' => null, 'email' => null, 'password' => null, 'search_text' => 'ada'], false],
    'not erased, no email' => [['erased_at' => null, 'name' => 'Ada', 'email' => null, 'password' => 'x'], false],
    'not erased, no name' => [['erased_at' => null, 'name' => null, 'email' => 'ada@example.com', 'password' => 'x'], false],
    'not erased, every field set' => [['erased_at' => null, 'name' => 'Ada', 'email' => 'someone-new@example.com', 'password' => 'x'], true],
]);

it('erases customers in the trash for the whole retention period, and nobody else', function (): void {
    config(['retention.periods.trashed_customers' => 90]);
    [$longTrashed, $recentlyTrashed, $longInactive] = $this->store->run(function (): array {
        $longTrashed = $this->customer;
        $recentlyTrashed = Customer::factory()->create(['email' => 'recent@example.com']);
        $longInactive = Customer::factory()->deactivated()->create(['email' => 'inactive@example.com', 'last_signed_in_at' => now()->subYears(5)]);

        $this->travelTo(now()->subDays(91), static fn () => $longTrashed->delete());
        $this->travelTo(now()->subDays(89), static fn () => $recentlyTrashed->delete());

        return [$longTrashed, $recentlyTrashed, $longInactive];
    });

    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());

    expect($this->store->run(static fn (): array => Customer::withTrashed()->orderBy('id')->get()->map(static fn (Customer $customer): array => [$customer->id, $customer->isErased()])->all()))
        ->toBe([[$longTrashed->id, true], [$recentlyTrashed->id, false], [$longInactive->id, false]]);
});

it('deletes data exports, file first, once they have been expired for the retention period', function (): void {
    config(['retention.periods.customer_data_exports' => 1]);
    $exportPath = exportFileOf(customerRequest('POST', 'account/data-exports', as: $this->customer)->json('data.id'));

    $this->travel(169 + 25)->hours();
    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());

    expect($this->store->run(static fn (): int => CustomerDataExport::query()->count()))->toBe(0)
        ->and($this->store->run(static fn (): bool => Storage::disk('local')->exists($exportPath)))->toBeFalse();
});

it('keeps the privacy routes to their guards and permissions', function (): void {
    $customerToken = customerTokenFor($this->customer);

    staffCustomerRequest('GET', "/{$this->customer->public_id}/personal-data", as: $customerToken)->assertUnauthorized();
    customerRequest('DELETE', 'account', ['current_password' => 'a-long-staff-password'], staffTokenFor($this->owner, 'first-store'))->assertUnauthorized();
    expect($this->store->run(static fn (): int => CustomerNote::query()->count()))->toBe(1);
});
