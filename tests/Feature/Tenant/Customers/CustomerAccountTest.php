<?php

declare(strict_types=1);

use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Customers\CustomerEmailChangedNotification;
use App\Tenant\Customers\CustomerEmailLinkNotification;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerEmailToken;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * A customer's own account (section 10): their profile, proving their email,
 * and moving the account to another email, which needs the current password,
 * happens only through a link sent to the new address, tells the old one,
 * and signs out every other device.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    $this->store->run(static fn () => StoreSettings::query()->whereKey(1)->update(['enabled_locales' => ['en', 'fr']]));
});

afterEach(function (): void {
    deleteAllStores();
});

it('emails a link at sign-up that confirms the email, without signing in, once', function (): void {
    $token = $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/accounts'), newCustomerDetails())->assertCreated()->json('data.token');
    tenancy()->end();

    customerRequest('GET', 'auth/me', as: $token)->assertOk()->assertJsonPath('data.email_verified', false);

    $link = emailedCustomerToken(CustomerEmailTokenPurpose::Verification, 'ada@example.com');
    customerRequest('POST', 'auth/email-verifications', ['token' => $link])->assertNoContent();
    customerRequest('POST', 'auth/email-verifications', ['token' => $link])->assertUnprocessable()->assertJsonPath('code', 'customer_email_link_invalid');

    customerRequest('GET', 'auth/me', as: $token)->assertOk()->assertJsonPath('data.email_verified', true);
    expect($this->store->run(static fn (): int => CustomerEmailToken::query()->count()))->toBe(0);
});

it('stores only the hash of an emailed link', function (): void {
    customerRequest('POST', 'account/email-verifications', as: firstStoreCustomer())->assertAccepted();

    $link = emailedCustomerToken(CustomerEmailTokenPurpose::Verification, 'ada@example.com');
    $stored = $this->store->run(static fn (): string => CustomerEmailToken::query()->sole()->token_hash);

    expect($stored)->toBe(hash('sha256', $link))->not->toContain($link);
});

it('refuses a verification link that expired, was replaced, is for an email change, or was sent to an address the account no longer has', function (Closure $spoil): void {
    $customer = firstStoreCustomer();
    customerRequest('POST', 'account/email-verifications', as: $customer)->assertAccepted();
    $link = emailedCustomerToken(CustomerEmailTokenPurpose::Verification, 'ada@example.com');

    // Returns another link to use instead, or null to use the one sent.
    $link = $spoil($customer) ?? $link;

    customerRequest('POST', 'auth/email-verifications', ['token' => $link])->assertUnprocessable()->assertJsonPath('code', 'customer_email_link_invalid');
    expect($this->store->run(static fn (): bool => $customer->refresh()->hasVerifiedEmail()))->toBeFalse();
})->with([
    'expired' => [function (): ?string {
        test()->travel(1441)->minutes();

        return null;
    }],
    'replaced by a newer link' => [function (Customer $customer): ?string {
        customerRequest('POST', 'account/email-verifications', as: $customer)->assertAccepted();

        return null;
    }],
    'an email change link' => [function (Customer $customer): string {
        customerRequest('POST', 'account/email-changes', ['email' => 'ada@new.example', 'current_password' => 'a-long-shopper-password'], $customer)->assertAccepted();

        return emailedCustomerToken(CustomerEmailTokenPurpose::EmailChange, 'ada@new.example');
    }],
    'the account\'s email changed since' => [function (Customer $customer): ?string {
        test()->store->run(static fn () => $customer->forceFill(['email' => 'someone-else@example.com'])->save());

        return null;
    }],
    'a deactivated account' => [function (Customer $customer): ?string {
        test()->store->run(static fn () => $customer->forceFill(['is_active' => false])->save());

        return null;
    }],
]);

it('sends a new verification link only to an unverified customer, a few times a minute', function (): void {
    customerRequest('POST', 'account/email-verifications', as: firstStoreCustomer(['email' => 'verified@example.com', 'email_verified_at' => now()]))
        ->assertConflict()
        ->assertJsonPath('code', 'customer_email_already_verified');

    $customer = firstStoreCustomer();
    $token = customerTokenFor($customer);

    foreach (range(1, 3) as $attempt) {
        customerRequest('POST', 'account/email-verifications', as: $token)->assertAccepted();
    }

    customerRequest('POST', 'account/email-verifications', as: $token)->assertTooManyRequests();
    Notification::assertSentOnDemandTimes(CustomerEmailLinkNotification::class, 3);
});

it('writes a customer\'s emails in their language', function (): void {
    customerRequest('POST', 'account/email-verifications', as: firstStoreCustomer(['locale' => 'fr']))->assertAccepted();

    Notification::assertSentOnDemand(CustomerEmailLinkNotification::class, static fn (CustomerEmailLinkNotification $notification): bool => $notification->locale === 'fr');
});

it('updates only the profile fields sent, storing a national phone number in E.164', function (): void {
    $customer = firstStoreCustomer(['phone' => '+2348012345678', 'locale' => 'fr']);

    customerRequest('PATCH', 'account', ['name' => 'Ada King', 'phone' => '0803 123 4567'], $customer)
        ->assertOk()
        ->assertJsonPath('data.name', 'Ada King')
        ->assertJsonPath('data.phone', '+2348031234567')
        ->assertJsonPath('data.locale', 'fr');

    customerRequest('PATCH', 'account', ['phone' => '+44 20 7946 0958', 'locale' => null], $customer)
        ->assertOk()
        ->assertJsonPath('data.phone', '+442079460958')
        ->assertJsonPath('data.locale', null);

    customerRequest('PATCH', 'account', ['phone' => null], $customer)->assertOk()->assertJsonPath('data.phone', null);
});

it('never changes the email through the profile', function (): void {
    $customer = firstStoreCustomer();

    customerRequest('PATCH', 'account', ['email' => 'mallory@example.com', 'email_verified_at' => now()->toIso8601String()], $customer)
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.email_verified', false);
});

it('refuses a profile with a phone that isn\'t one, a language the store doesn\'t publish in, or an empty name', function (array $changes, string $field): void {
    customerRequest('PATCH', 'account', $changes, firstStoreCustomer())->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'not a phone' => [['phone' => '12345'], 'phone'],
    'language not published' => [['locale' => 'de'], 'locale'],
    'empty name' => [['name' => ''], 'name'],
]);

it('keeps a phone that isn\'t in E.164 out of the table', function (): void {
    expect(fn () => $this->store->run(static fn () => DB::table('customers')->insert([
        'public_id' => (string) Str::ulid(), 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x', 'phone' => '08031234567', 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class, 'customers_phone_e164_check');
});

it('moves the account to a new email only once the link sent there is used, signed in to the same account', function (): void {
    $customer = firstStoreCustomer(['email_verified_at' => now()]);
    $thisDevice = customerTokenFor($customer);
    $otherDevice = customerTokenFor($customer);
    $this->store->run(static fn () => DB::table('customer_password_reset_tokens')->insert(['email' => 'ada@example.com', 'token' => 'hashed', 'created_at' => now()]));

    customerRequest('POST', 'account/email-changes', ['email' => ' Ada@New.Example ', 'current_password' => 'a-long-shopper-password'], $thisDevice)->assertAccepted();

    customerRequest('GET', 'auth/me', as: $thisDevice)->assertJsonPath('data.email', 'ada@example.com');
    $link = emailedCustomerToken(CustomerEmailTokenPurpose::EmailChange, 'ada@new.example');

    customerRequest('POST', 'account/email-changes/confirmations', ['token' => $link], $thisDevice)
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@new.example')
        ->assertJsonPath('data.email_verified', true);

    customerRequest('GET', 'auth/me', as: $thisDevice)->assertOk();
    customerRequest('GET', 'auth/me', as: $otherDevice)->assertUnauthorized();
    customerRequest('POST', 'account/email-changes/confirmations', ['token' => $link], $thisDevice)->assertUnprocessable()->assertJsonPath('code', 'customer_email_link_invalid');

    Notification::assertSentOnDemand(CustomerEmailChangedNotification::class, static fn (CustomerEmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routeNotificationFor('mail') === 'ada@example.com'
        && ! str_contains(implode(' ', $notification->toMail($notifiable)->introLines), 'ada@new.example'));
    expect($this->store->run(static fn (): int => DB::table('customer_password_reset_tokens')->count()))->toBe(0);
});

it('needs the current password to start an email change, and locks after five wrong ones', function (): void {
    $customer = firstStoreCustomer();
    $token = customerTokenFor($customer);

    foreach (range(1, 5) as $attempt) {
        customerRequest('POST', 'account/email-changes', ['email' => 'ada@new.example', 'current_password' => 'wrong-password-'.$attempt], $token)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'current_password_incorrect');
        $this->travel(61)->seconds();
    }

    customerRequest('POST', 'account/email-changes', ['email' => 'ada@new.example', 'current_password' => 'a-long-shopper-password'], $token)
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_incorrect_attempts');
    Notification::assertNothingSent();
});

it('refuses to move the account to its own email or one another account has', function (string $email): void {
    firstStoreCustomer(['email' => 'grace@example.com']);

    customerRequest('POST', 'account/email-changes', ['email' => $email, 'current_password' => 'a-long-shopper-password'], firstStoreCustomer())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
})->with(['its own' => 'ADA@example.com', 'taken' => 'grace@example.com']);

it('refuses an email change link used by another account, after it expired, or once another account took the address', function (Closure $spoil): void {
    $customer = firstStoreCustomer();
    customerRequest('POST', 'account/email-changes', ['email' => 'ada@new.example', 'current_password' => 'a-long-shopper-password'], $customer)->assertAccepted();
    $link = emailedCustomerToken(CustomerEmailTokenPurpose::EmailChange, 'ada@new.example');

    // Returns the account to confirm as instead, or null to confirm as the customer.
    $confirmAs = $spoil() ?? $customer;

    customerRequest('POST', 'account/email-changes/confirmations', ['token' => $link], $confirmAs)->assertUnprocessable();
    expect($this->store->run(static fn (): string => $customer->refresh()->email))->toBe('ada@example.com');
})->with([
    'another account' => [fn (): Customer => firstStoreCustomer(['email' => 'mallory@example.com'])],
    'expired' => [function (): ?Customer {
        test()->travel(61)->minutes();

        return null;
    }],
    'address taken since' => [function (): ?Customer {
        firstStoreCustomer(['email' => 'ada@new.example']);

        return null;
    }],
]);

it('refuses a verification link as an email change confirmation, though it is the same account\'s', function (): void {
    $customer = firstStoreCustomer();
    customerRequest('POST', 'account/email-verifications', as: $customer)->assertAccepted();
    $link = emailedCustomerToken(CustomerEmailTokenPurpose::Verification, 'ada@example.com');

    customerRequest('POST', 'account/email-changes/confirmations', ['token' => $link], $customer)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'customer_email_link_invalid');
    expect($this->store->run(static fn (): bool => $customer->refresh()->hasVerifiedEmail()))->toBeFalse();
});

it('deletes emailed links once they have been expired for the whole retention period', function (): void {
    config(['retention.periods.customer_email_tokens' => 1]);
    $customer = firstStoreCustomer();
    customerRequest('POST', 'account/email-verifications', as: $customer)->assertAccepted();
    customerRequest('POST', 'account/email-changes', ['email' => 'ada@new.example', 'current_password' => 'a-long-shopper-password'], $customer)->assertAccepted();

    // The email change link expired after an hour, so a day later it goes; the verification link expires after a day, so it stays.
    $this->travelTo(now()->addDay()->addHours(2));

    $this->store->run(static function (): void {
        PurgeExpiredRecords::dispatchSync();

        expect(CustomerEmailToken::query()->pluck('purpose')->all())->toBe([CustomerEmailTokenPurpose::Verification]);
    });
});

it('keeps the account routes to the customer guard', function (): void {
    $staffToken = $this->store->run(static fn (): string => app(App\Shared\Auth\AccessTokenIssuer::class)->issue(StaffMember::factory()->create(), StaffMember::GUARD, 'test')->plainTextToken);

    customerRequest('PATCH', 'account', ['name' => 'Mallory'], $staffToken)->assertUnauthorized();
    customerRequest('POST', 'account/email-verifications', as: $staffToken)->assertUnauthorized();
    customerRequest('POST', 'account/email-changes', ['email' => 'x@example.com', 'current_password' => 'x'], $staffToken)->assertUnauthorized();
});

it('never lets a customer of one store use their token or links at another', function (): void {
    createStore('second-store');
    $customer = firstStoreCustomer();
    $token = customerTokenFor($customer);
    customerRequest('POST', 'account/email-verifications', as: $token)->assertAccepted();
    $link = emailedCustomerToken(CustomerEmailTokenPurpose::Verification, 'ada@example.com');

    customerRequest('PATCH', 'account', ['name' => 'Mallory'], $token, 'second-store')->assertUnauthorized();
    customerRequest('POST', 'auth/email-verifications', ['token' => $link], subdomain: 'second-store')->assertUnprocessable();
});
