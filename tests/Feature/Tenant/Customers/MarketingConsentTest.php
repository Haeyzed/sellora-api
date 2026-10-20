<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Customers\Actions\RecordMarketingConsent;
use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Enums\MarketingConsentSource;
use App\Tenant\Customers\Enums\MarketingConsentStatus;
use App\Tenant\Customers\Models\MarketingConsent;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Marketing consent (section 3.3): events that are never edited (channel,
 * contact, granted or withdrawn, source, the exact wording shown and its
 * language, when). Consent belongs to the contact, so a guest can give it
 * at checkout. Staff record withdrawals, never grants. Only erasure deletes
 * events. Single opt-in for now.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('first-store', ['country_code' => 'NG', 'name' => 'Ada Fabrics']);
    $this->store->run(static fn () => StoreSettings::query()->whereKey(1)->update(['enabled_locales' => ['en', 'fi']]));
    $this->customer = firstStoreCustomer();

    [$this->owner, $this->viewer] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        $support = Role::findOrCreate('Support', StaffMember::GUARD);
        $support->givePermissionTo(Permission::findByName('customers.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($support);

        return [$owner, $viewer];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('shows each channel\'s current answer with the text to agree to', function (): void {
    customerRequest('GET', 'account/marketing-consents', as: $this->customer)
        ->assertOk()
        ->assertJsonPath('data.0', ['channel' => 'email', 'contact' => 'ada@example.com', 'granted' => false, 'answered_at' => null, 'wording' => 'Send me news and offers from Ada Fabrics by email. I can unsubscribe at any time.'])
        ->assertJsonPath('data.1.channel', 'sms')
        ->assertJsonPath('data.1.contact', null);
});

it('records each answer as it was given, with the exact wording and its language', function (): void {
    customerRequest('PUT', 'account/marketing-consents/email', ['granted' => true], $this->customer)->assertOk()->assertJsonPath('data.granted', true);
    customerRequest('PUT', 'account/marketing-consents/email', ['granted' => false], $this->customer)->assertOk()->assertJsonPath('data.granted', false);

    $events = $this->store->run(static fn (): array => MarketingConsent::query()->orderBy('id')->get()
        ->map(static fn (MarketingConsent $event): array => [$event->status->value, $event->source->value, $event->contact, $event->wording, $event->locale])->all());

    expect($events)->toBe([
        ['granted', 'account', 'ada@example.com', 'Send me news and offers from Ada Fabrics by email. I can unsubscribe at any time.', 'en'],
        ['withdrawn', 'account', 'ada@example.com', 'Send me news and offers from Ada Fabrics by email. I can unsubscribe at any time.', 'en'],
    ]);
});

it('records the language the wording was really shown in when there is no translation', function (): void {
    // The store offers Finnish, but nothing is translated into it.
    $this->withHeader('Accept-Language', 'fi');
    customerRequest('PUT', 'account/marketing-consents/email', ['granted' => true], $this->customer)->assertOk()->assertHeader('Content-Language', 'fi');

    expect($this->store->run(static fn (): ?string => MarketingConsent::query()->sole()->locale))->toBe('en');
});

it('needs a phone on the account for text messages', function (): void {
    customerRequest('PUT', 'account/marketing-consents/sms', ['granted' => true], $this->customer)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'marketing_contact_missing');

    $this->store->run(fn () => $this->customer->forceFill(['phone' => '+2348031234567'])->save());

    customerRequest('PUT', 'account/marketing-consents/sms', ['granted' => true], $this->customer)
        ->assertOk()
        ->assertJsonPath('data.contact', '+2348031234567')
        ->assertJsonPath('data.granted', true);
});

it('counts a guest\'s answer at checkout for the account with that email, and starts afresh after an email change', function (): void {
    $this->store->run(static fn () => app(RecordMarketingConsent::class)->handle(MarketingChannel::Email, 'ada@example.com', MarketingConsentStatus::Granted, MarketingConsentSource::Checkout, 'en'));

    customerRequest('GET', 'account/marketing-consents', as: $this->customer)->assertJsonPath('data.0.granted', true);
    staffCustomerRequest('GET', "/{$this->customer->public_id}/marketing-consents", as: $this->viewer)
        ->assertOk()
        ->assertJsonPath('data.0.source', 'checkout');

    $this->store->run(fn () => $this->customer->forceFill(['email' => 'ada@new.example'])->save());
    customerRequest('GET', 'account/marketing-consents', as: $this->customer)->assertJsonPath('data.0.granted', false);
});

it('lets staff record a withdrawal the customer asked for, but never a grant', function (): void {
    customerRequest('PUT', 'account/marketing-consents/email', ['granted' => true], $this->customer)->assertOk();

    staffCustomerRequest('POST', "/{$this->customer->public_id}/marketing-consents/withdrawals", ['channel' => 'email'], $this->viewer)->assertForbidden();
    staffCustomerRequest('POST', "/{$this->customer->public_id}/marketing-consents/withdrawals", ['channel' => 'email'], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.status', 'withdrawn')
        ->assertJsonPath('data.source', 'staff')
        ->assertJsonPath('data.wording', null)
        ->assertJsonPath('data.recorded_by_staff', true);

    customerRequest('GET', 'account/marketing-consents', as: $this->customer)->assertJsonPath('data.0.granted', false);
    expect($this->store->run(static fn (): array => Activity::query()->inLog('customers')->pluck('event')->all()))->toBe(['marketing_consent_withdrawn'])
        ->and(fn () => $this->store->run(fn () => app(RecordMarketingConsent::class)->handle(MarketingChannel::Email, 'ada@example.com', MarketingConsentStatus::Granted, MarketingConsentSource::Staff, 'en', $this->customer, $this->owner)))
        ->toThrow(InvalidArgumentException::class);
});

it('never lets an event be changed or deleted outside erasure', function (Closure $tamper, string $message): void {
    customerRequest('PUT', 'account/marketing-consents/email', ['granted' => true], $this->customer)->assertOk();

    expect(fn () => $this->store->run($tamper))->toThrow(QueryException::class, $message);
    expect($this->store->run(static fn (): array => MarketingConsent::query()->pluck('status')->map->value->all()))->toBe(['granted']);
})->with([
    'changed' => [fn () => DB::table('marketing_consents')->update(['status' => 'withdrawn']), 'never changed'],
    'deleted' => [fn () => DB::table('marketing_consents')->delete(), 'deleted only by erasure'],
    'emptied' => [fn () => DB::statement('truncate marketing_consents'), 'deleted only by erasure'],
    'deleted after an erasure ended' => [function (): void {
        app(PersonalDataErasure::class)->run(static fn (): null => null);
        DB::table('marketing_consents')->delete();
    }, 'deleted only by erasure'],
    'deleted after an erasure ended, in the same larger transaction' => [function (): void {
        DB::transaction(static function (): void {
            app(PersonalDataErasure::class)->run(static fn (): null => null);
            DB::table('marketing_consents')->delete();
        });
    }, 'deleted only by erasure'],
]);

it('lets erasure delete events, inside the erasure runner\'s transaction', function (): void {
    customerRequest('PUT', 'account/marketing-consents/email', ['granted' => true], $this->customer)->assertOk();

    $this->store->run(static fn () => app(PersonalDataErasure::class)->run(static fn (): int => DB::table('marketing_consents')->delete()));

    expect($this->store->run(static fn (): int => MarketingConsent::query()->count()))->toBe(0);
});

it('rolls the whole erasure back when part of it fails', function (): void {
    customerRequest('PUT', 'account/marketing-consents/email', ['granted' => true], $this->customer)->assertOk();

    expect(fn () => $this->store->run(static fn () => app(PersonalDataErasure::class)->run(static function (): never {
        DB::table('marketing_consents')->delete();

        throw new RuntimeException('Part of the erasure failed.');
    })))->toThrow(RuntimeException::class, 'Part of the erasure failed.');

    expect($this->store->run(static fn (): int => MarketingConsent::query()->count()))->toBe(1);
});

it('refuses a grant without its wording, or a staff grant, at the database too', function (array $row, string $constraint): void {
    expect(fn () => $this->store->run(static fn () => DB::table('marketing_consents')->insert([
        'public_id' => (string) Str::ulid(), 'channel' => 'email', 'contact' => 'ada@example.com', 'status' => 'granted', 'source' => 'account', 'wording' => 'x', 'locale' => 'en', ...$row,
    ])))->toThrow(QueryException::class, $constraint);
})->with([
    'no wording' => [['wording' => null], 'marketing_consents_granted_wording_check'],
    'granted by staff' => [['source' => 'staff', 'recorded_by_staff_id' => 1], 'marketing_consents_staff_withdraw_only_check'],
]);

it('keeps the consent routes to their guards and stores', function (): void {
    createStore('second-store');
    $customerToken = customerTokenFor($this->customer);

    customerRequest('GET', 'account/marketing-consents', as: staffTokenFor($this->owner, 'first-store'))->assertUnauthorized();
    customerRequest('GET', 'account/marketing-consents', as: $customerToken, subdomain: 'second-store')->assertUnauthorized();
    staffCustomerRequest('GET', "/{$this->customer->public_id}/marketing-consents", as: $customerToken)->assertUnauthorized();
});
