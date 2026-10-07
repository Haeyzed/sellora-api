<?php

declare(strict_types=1);

use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalAcceptance;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\NoDatabaseServerAvailableException;
use App\Landlord\Tenancy\Jobs\ProvisionStore;
use App\Landlord\Tenancy\Models\DatabaseServer;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\StoreReadyNotification;
use App\Landlord\Tenancy\StoreRegistrationCodeNotification;
use App\Shared\Money\TaxMode;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Enums\WeightUnit;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/*
 * Section 9.1: a store is registered in two steps (sign-up, then the emailed
 * code), set up on the queue on a database server in its hosting region, and
 * serves no requests until every step has succeeded. The subdomain is the
 * merchant's choice, never silently changed; the legal documents in force
 * must all be accepted; and each email may own a limited number of stores.
 */
uses(DatabaseTruncation::class);

const OWNER_PASSWORD = 'a-long-owner-password';

beforeEach(function (): void {
    Notification::fake();
    // These tests sign up more often than one visitor may in a minute.
    config(['api.rate_limits.registration' => 100]);

    $this->databaseServer = openStoreRegistration();
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * Everything registration needs: a free plan, a version in force of every legal document, a database server, and the test countries (seedWorld()).
 */
function openStoreRegistration(): DatabaseServer
{
    Plan::factory()->create(['code' => 'free', 'name' => 'Free']);

    foreach (LegalDocumentType::cases() as $type) {
        LegalDocument::factory()->ofType($type)->inForce()->create();
    }

    seedWorld();

    return DatabaseServer::factory()->inRegion('africa')->create(['name' => 'africa-1', 'capacity' => 10]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storeSignUp(array $overrides = []): array
{
    return [
        'store_name' => 'Ada Fabrics',
        'subdomain' => 'ada-fabrics',
        'owner_name' => 'Ada Obi',
        'email' => 'ada@example.com',
        'password' => OWNER_PASSWORD,
        'password_confirmation' => OWNER_PASSWORD,
        'country_code' => 'NG',
        'hosting_region' => 'africa',
        'accepted_legal_documents' => array_map(static fn (LegalDocument $legalDocument): string => $legalDocument->public_id, array_values(LegalDocument::inForce())),
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function signUpForStore(array $overrides = []): TestResponse
{
    return test()->postJson(centralUrl('/api/v1/store-registrations'), storeSignUp($overrides));
}

function confirmStoreSignUp(string $storeRegistrationId, string $code): TestResponse
{
    return test()->postJson(centralUrl("/api/v1/store-registrations/{$storeRegistrationId}/verification"), ['code' => $code]);
}

/**
 * The last code emailed to the address.
 */
function lastStoreRegistrationCode(string $email = 'ada@example.com'): string
{
    $codes = [];

    Notification::assertSentOnDemand(StoreRegistrationCodeNotification::class, static function (StoreRegistrationCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$codes): bool {
        if ($notifiable->routes['mail'] === $email) {
            $codes[] = $notification->code();
        }

        return true;
    });

    return $codes === [] ? throw new RuntimeException("No code was sent to {$email}.") : end($codes);
}

it('registers a store, sets it up on a server in its region, and lets the owner sign in', function (): void {
    $this->getJson(centralUrl('/api/v1/hosting-regions'))->assertOk()->assertExactJson(['data' => [['code' => 'africa', 'name' => 'Africa']]]);
    $this->getJson(centralUrl('/api/v1/legal-documents'))->assertOk()->assertJsonCount(2, 'data');

    $storeRegistrationId = signUpForStore()
        ->assertAccepted()
        ->assertJsonPath('data.status', 'awaiting_verification')
        ->assertJsonPath('data.domain', 'ada-fabrics.'.config('platform.domain'))
        ->json('data.id');

    expect(Tenant::query()->count())->toBe(0);

    confirmStoreSignUp($storeRegistrationId, lastStoreRegistrationCode())
        ->assertAccepted()
        ->assertJsonPath('data.status', 'active');

    $store = Tenant::query()->sole();
    expect($store->status)->toBe(TenantStatus::Active)
        ->and($store->name)->toBe('Ada Fabrics')
        ->and($store->hosting_region)->toBe('africa')
        ->and($store->currency_code)->toBe('NGN')
        ->and($store->timezone)->toBe('Africa/Lagos')
        ->and($store->locale)->toBe('en')
        ->and($store->owner_email)->toBe('ada@example.com')
        ->and($store->database_server_id)->toBe($this->databaseServer->id)
        ->and($store->database()->getTemplateConnectionName())->toBe('database_server_'.$this->databaseServer->id)
        ->and($this->databaseServer->refresh()->tenant_count)->toBe(1)
        ->and(Subscription::query()->where('tenant_id', $store->id)->sole()->status)->toBe(SubscriptionStatus::Active)
        ->and(StoreRegistration::query()->sole()->password_hash)->toBeNull()
        ->and(LegalAcceptance::query()->where('tenant_id', $store->id)->whereNull('store_registration_id')->count())->toBe(2)
        ->and(Activity::query()->where('subject_id', $store->id)->pluck('event')->all())->toBe(['store_registered', 'store_provisioned']);

    $this->postJson(storeUrl('ada-fabrics', '/api/v1/staff/auth/tokens'), ['email' => 'ada@example.com', 'password' => OWNER_PASSWORD])->assertOk();
    tenancy()->end();

    expect($store->run(static fn (): bool => StaffMember::query()->sole()->hasRole(StaffRole::Owner->value)))->toBeTrue();
    Notification::assertSentOnDemand(StoreReadyNotification::class, static fn (StoreReadyNotification $notification): bool => $notification->dashboardUrl() === 'https://ada-fabrics.'.config('platform.domain').'/admin');
});

it('counts every wrong code, even though the request fails, and needs a new code after five', function (): void {
    $storeRegistrationId = signUpForStore()->json('data.id');
    $code = lastStoreRegistrationCode();
    $wrongCode = $code === '000000' ? '111111' : '000000';

    foreach (range(1, StoreRegistration::MAX_VERIFICATION_ATTEMPTS) as $attempt) {
        confirmStoreSignUp($storeRegistrationId, $wrongCode)->assertUnprocessable()->assertJsonPath('code', 'store_registration_code_invalid');
    }

    expect(StoreRegistration::query()->sole()->verification_attempts)->toBe(StoreRegistration::MAX_VERIFICATION_ATTEMPTS);
    confirmStoreSignUp($storeRegistrationId, $code)->assertUnprocessable()->assertJsonPath('code', 'store_registration_code_invalid');

    $this->travel(61)->seconds();
    $this->postJson(centralUrl("/api/v1/store-registrations/{$storeRegistrationId}/codes"))->assertAccepted();

    confirmStoreSignUp($storeRegistrationId, $code)->assertUnprocessable();
    confirmStoreSignUp($storeRegistrationId, lastStoreRegistrationCode())->assertAccepted()->assertJsonPath('data.status', 'active');
    confirmStoreSignUp($storeRegistrationId, lastStoreRegistrationCode())->assertUnprocessable()->assertJsonPath('code', 'store_registration_code_invalid');
});

it('refuses an expired code, sends at most one code a minute, and revives an expired sign-up with a new code', function (): void {
    $storeRegistrationId = signUpForStore()->json('data.id');

    $this->postJson(centralUrl("/api/v1/store-registrations/{$storeRegistrationId}/codes"))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'verification_code_recently_sent');

    $this->travel(61)->minutes();
    confirmStoreSignUp($storeRegistrationId, lastStoreRegistrationCode())->assertUnprocessable()->assertJsonPath('code', 'store_registration_expired');
    $this->getJson(centralUrl("/api/v1/store-registrations/{$storeRegistrationId}"))->assertOk()->assertJsonPath('data.status', 'expired');

    $this->postJson(centralUrl("/api/v1/store-registrations/{$storeRegistrationId}/codes"))->assertAccepted()->assertJsonPath('data.status', 'awaiting_verification');
    confirmStoreSignUp($storeRegistrationId, lastStoreRegistrationCode())->assertAccepted();
});

it('holds a subdomain for a sign-up in progress, and refuses taken, reserved and malformed subdomains', function (): void {
    $existingStore = createStoreRecord();
    $existingStore->domains()->create(['domain' => Tenant::platformDomainFor('taken-store')]);

    signUpForStore(['subdomain' => 'taken-store'])->assertUnprocessable()->assertJsonPath('code', 'subdomain_taken')->assertJsonValidationErrors('subdomain');

    signUpForStore()->assertAccepted();
    signUpForStore(['email' => 'someone-else@example.com'])->assertUnprocessable()->assertJsonPath('code', 'subdomain_taken');

    foreach (['admin', 'www', 'ab', '-ada', 'ada-', 'ada--fabrics', 'ada_fabrics', 'ada.fabrics'] as $subdomain) {
        signUpForStore(['subdomain' => $subdomain, 'email' => 'other@example.com'])->assertJsonValidationErrors('subdomain');
    }

    signUpForStore(['subdomain' => '  Ada-Shoes ', 'email' => 'other@example.com'])->assertAccepted()->assertJsonPath('data.domain', 'ada-shoes.'.config('platform.domain'));
});

it('needs exactly the legal documents in force, and keeps a version in force until its replacement takes effect', function (): void {
    $termsInForce = LegalDocument::inForce()[LegalDocumentType::TermsOfService->value];

    signUpForStore(['accepted_legal_documents' => [$termsInForce->public_id]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'legal_documents_not_accepted');

    LegalDocument::factory()->ofType(LegalDocumentType::TermsOfService)->create(['published_at' => now(), 'effective_at' => now()->addMonth()]);

    expect(LegalDocument::inForce()[LegalDocumentType::TermsOfService->value]->is($termsInForce))->toBeTrue();
    signUpForStore()->assertAccepted();
});

it('stays closed until the starting plan and every legal document exist', function (): void {
    LegalDocument::query()->where('type', LegalDocumentType::PrivacyPolicy)->delete();
    signUpForStore()->assertServiceUnavailable()->assertJsonPath('code', 'store_registration_closed');

    LegalDocument::factory()->ofType(LegalDocumentType::PrivacyPolicy)->inForce()->create();
    Plan::query()->update(['is_active' => false]);
    signUpForStore()->assertServiceUnavailable()->assertJsonPath('code', 'store_registration_closed');
});

it('only offers regions with a database server accepting new stores', function (): void {
    $this->databaseServer->update(['capacity' => 0]);
    DatabaseServer::factory()->inRegion('eu')->create(['accepting_new_tenants' => false]);

    $this->getJson(centralUrl('/api/v1/hosting-regions'))->assertOk()->assertJsonCount(0, 'data');
    signUpForStore()->assertUnprocessable()->assertJsonPath('code', 'hosting_region_unavailable')->assertJsonValidationErrors('hosting_region');
    signUpForStore(['hosting_region' => 'mars'])->assertJsonValidationErrors('hosting_region');
});

it('limits how many stores one email can own, counting sign-ups in progress', function (): void {
    config(['platform.store_registration.max_stores_per_email' => 2]);
    createStoreRecord()->update(['owner_email' => 'ada@example.com']);

    signUpForStore()->assertAccepted();
    signUpForStore(['subdomain' => 'ada-shoes', 'email' => ' ADA@example.com'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'stores_per_email_limit_reached');
});

it('asks for the timezone when the country has several, and only accepts the country\'s own', function (): void {
    signUpForStore(['country_code' => 'US'])->assertJsonValidationErrors('timezone');
    signUpForStore(['country_code' => 'US', 'timezone' => 'Africa/Lagos'])->assertJsonValidationErrors('timezone');
    signUpForStore(['country_code' => 'US', 'timezone' => 'US/Eastern'])->assertJsonValidationErrors('timezone');
    signUpForStore(['country_code' => 'FR'])->assertJsonValidationErrors('country_code');

    signUpForStore(['country_code' => 'us', 'timezone' => 'America/Chicago'])->assertAccepted();

    expect(StoreRegistration::query()->sole())
        ->country_code->toBe('US')
        ->timezone->toBe('America/Chicago');
});

it('does not serve a store until it is set up', function (): void {
    $store = Tenant::factory()->provisioning()->create();
    $store->domains()->create(['domain' => Tenant::platformDomainFor('new-store')]);

    $this->postJson(storeUrl('new-store', '/api/v1/staff/auth/tokens'), ['email' => 'ada@example.com', 'password' => OWNER_PASSWORD])
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'store_unavailable');
});

it('can set a store up again after a failure without creating a second owner', function (): void {
    confirmStoreSignUp(signUpForStore()->json('data.id'), lastStoreRegistrationCode())->assertAccepted();
    $store = Tenant::query()->sole();

    // As if the last step failed after the owner was created.
    $store->update(['status' => TenantStatus::Provisioning]);
    ProvisionStore::dispatch($store->id);

    expect($store->refresh()->status)->toBe(TenantStatus::Active)
        ->and($store->run(static fn (): int => StaffMember::query()->count()))->toBe(1)
        ->and($this->databaseServer->refresh()->tenant_count)->toBe(1);
});

it('deletes abandoned sign-ups with their acceptances, but keeps one whose store still needs its password', function (): void {
    signUpForStore();
    signUpForStore(['subdomain' => 'ada-shoes']);
    $awaitingSetUp = StoreRegistration::query()->where('subdomain', 'ada-shoes')->sole();
    $awaitingSetUp->forceFill(['verified_at' => now()])->save();

    $this->travel(61)->minutes();
    $this->travel(config()->integer('retention.periods.store_registrations') + 1)->days();
    PurgeExpiredRecords::dispatchSync();

    expect(StoreRegistration::query()->pluck('subdomain')->all())->toBe(['ada-shoes'])
        ->and(LegalAcceptance::query()->pluck('store_registration_id')->unique()->all())->toBe([$awaitingSetUp->id]);
});

it('marks a store as failed when setting it up fails for good', function (): void {
    $storeRegistrationId = signUpForStore()->json('data.id');
    $this->databaseServer->update(['accepting_new_tenants' => false]);

    // Tests run the queue synchronously, so the failure surfaces in this request; for real it happens later, on the queue.
    expect(confirmStoreSignUp($storeRegistrationId, lastStoreRegistrationCode())->exception)->toBeInstanceOf(NoDatabaseServerAvailableException::class);

    $store = Tenant::query()->sole();
    expect($store->status)->toBe(TenantStatus::ProvisioningFailed)
        ->and(StoreRegistration::query()->sole()->password_hash)->not->toBeNull()
        ->and(Activity::query()->where('subject_id', $store->id)->pluck('event')->all())->toBe(['store_registered', 'store_provisioning_failed']);
});

it('sets the store\'s currency and content language from its country', function (): void {
    $storeRegistrationId = signUpForStore(['country_code' => 'DE'])->assertAccepted()->json('data.id');
    confirmStoreSignUp($storeRegistrationId, lastStoreRegistrationCode())->assertAccepted();

    expect(Tenant::query()->sole())
        ->currency_code->toBe('EUR')
        ->timezone->toBe('Europe/Berlin')
        ->locale->toBe('de');

    // Setting the store up gives it its first settings, from the same defaults.
    expect(Tenant::query()->sole()->run(static fn (): array => StoreSettings::query()->sole()->only(['currency_code', 'timezone', 'default_locale', 'enabled_locales', 'tax_mode', 'weight_unit'])))
        ->toBe(['currency_code' => 'EUR', 'timezone' => 'Europe/Berlin', 'default_locale' => 'de', 'enabled_locales' => ['de'], 'tax_mode' => TaxMode::Inclusive, 'weight_unit' => WeightUnit::Kilogram]);
});
