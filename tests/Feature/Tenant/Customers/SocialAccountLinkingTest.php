<?php

declare(strict_types=1);

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Customers\SocialLinkNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 10b.4: connecting providers to accounts. An existing account with the
 * provider's email is connected only once its owner proves it is theirs:
 * with its password, or with an emailed link only when the provider is
 * trusted and says the email is verified (the email says plainly someone is
 * connecting a sign-in, and to ignore it otherwise). Connecting never changes
 * the account's email nor marks it verified. Signed-in customers connect
 * providers through a sign-in started for them, and disconnect one only
 * while another way to sign in remains, even when two disconnections race.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    Notification::fake();
    configureSocialSignInApps();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('social-store', ['country_code' => 'NG']);
    offerSocialSignIn($this->store, ['google', 'apple']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('connects the provider once the owner proves the account with its password, never verifying it nor changing its email', function (): void {
    $this->store->run(static fn () => Customer::factory()->create(['name' => 'Ada Obi', 'email' => 'ada@example.com', 'password' => 'a-long-shopper-password', 'email_verified_at' => null]));
    [$ticket, $proofs] = linkTicketFor(['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Someone']);
    expect($proofs)->toBe(['password', 'email_link']);

    customerRequest('POST', 'auth/social/link-confirmations', ['ticket' => $ticket, 'password' => 'not-the-password-at-all'], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'current_password_incorrect');
    expect($this->store->run(static fn (): int => CustomerIdentity::query()->count()))->toBe(0);

    $linked = customerRequest('POST', 'auth/social/link-confirmations', ['ticket' => $ticket, 'password' => 'a-long-shopper-password'], subdomain: 'social-store')->assertOk();

    expect($linked->json('data.status'))->toBe('signed_in')
        ->and($this->store->run(static fn (): array => [Customer::query()->sole()->only(['name', 'email']), Customer::query()->sole()->email_verified_at, CustomerIdentity::query()->sole()->provider_user_id]))
        ->toBe([['name' => 'Ada Obi', 'email' => 'ada@example.com'], null, 'google-ada']);

    // A ticket works once.
    customerRequest('POST', 'auth/social/link-confirmations', ['ticket' => $ticket, 'password' => 'a-long-shopper-password'], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');

    // Signing in with Google now opens the account.
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Someone']);
    finishSocialSignIn('google')->assertOk()->assertJsonPath('data.new_account', false);
});

it('emails the account a link to confirm, saying plainly what is happening, when a trusted provider vouches for the email', function (): void {
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password']));
    [$ticket] = linkTicketFor(['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);

    customerRequest('POST', 'auth/social/link-emails', ['ticket' => $ticket], subdomain: 'social-store')->assertAccepted();

    $token = null;
    Notification::assertSentOnDemand(SocialLinkNotification::class, static function (SocialLinkNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$token): bool {
        $mail = $notification->toMail($notifiable);
        $token = emailedLinkFragment($notification->url())['token'] ?? null;

        return $notifiable->routeNotificationFor('mail') === 'ada@example.com'
            && str_contains((string) $mail->subject, 'connecting a sign-in')
            && str_contains(implode(' ', $mail->introLines), 'Google')
            && str_contains(implode(' ', $mail->outroLines), 'ignore this email');
    });

    $linked = customerRequest('POST', 'auth/social/link-email-confirmations', ['token' => $token], subdomain: 'social-store')->assertOk();
    expect($linked->json('data.status'))->toBe('signed_in')
        ->and($this->store->run(static fn (): string => CustomerIdentity::query()->sole()->provider_user_id))->toBe('google-ada');

    customerRequest('POST', 'auth/social/link-email-confirmations', ['token' => $token], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');
});

it('lets only the password prove ownership when the provider is untrusted or doesn\'t vouch for the email', function (array $person, array $trusted): void {
    config(['customers.social.trusted_email_providers' => $trusted]);
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password']));
    [$ticket, $proofs] = linkTicketFor($person);

    expect($proofs)->toBe(['password']);
    customerRequest('POST', 'auth/social/link-emails', ['ticket' => $ticket], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_link_email_not_allowed');
    Notification::assertNothingSent();
})->with([
    'an untrusted provider' => [['sub' => 'google-x', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'X'], ['apple']],
    'an unverified email' => [['sub' => 'google-x', 'email' => 'ada@example.com', 'email_verified' => false, 'name' => 'X'], ['google', 'apple']],
]);

it('stops an emailed link once the account\'s email changed', function (): void {
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password']));
    [$ticket] = linkTicketFor(['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    customerRequest('POST', 'auth/social/link-emails', ['ticket' => $ticket], subdomain: 'social-store')->assertAccepted();
    $token = null;
    Notification::assertSentOnDemand(SocialLinkNotification::class, static function (SocialLinkNotification $notification) use (&$token): bool {
        $token = emailedLinkFragment($notification->url())['token'] ?? null;

        return true;
    });

    $this->store->run(static fn () => Customer::query()->sole()->update(['email' => 'ada.new@example.com']));

    customerRequest('POST', 'auth/social/link-email-confirmations', ['token' => $token], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');
    expect($this->store->run(static fn (): int => CustomerIdentity::query()->count()))->toBe(0);
});

it('connects a provider to the signed-in customer through a sign-in started for them, and refuses one connected elsewhere', function (): void {
    [$ada] = customerWithIdentities([], 'a-long-shopper-password');
    [$eve] = customerWithIdentities(['google'], email: 'eve@example.com');
    $startLink = static function (Customer $customer): string {
        $url = (string) customerRequest('POST', 'account/identities/google/authorizations', [], $customer, 'social-store')->assertCreated()->json('data.authorization_url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) $query['state'];
    };

    // Eve's Google account is hers.
    fakeSocialProvider('google', ['sub' => 'google-'.$eve->id, 'email' => 'eve@example.com', 'email_verified' => true, 'name' => 'Eve']);
    customerRequest('POST', 'account/identities/google', ['state' => $startLink($ada), 'code' => 'c'], $ada, 'social-store')->assertConflict()->assertJsonPath('code', 'social_identity_in_use');

    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada.gmail@example.com', 'email_verified' => true, 'name' => 'Ada']);
    $linked = customerRequest('POST', 'account/identities/google', ['state' => $startLink($ada), 'code' => 'c'], $ada, 'social-store')->assertCreated();

    expect($linked->json('data'))->toMatchArray(['provider' => 'google', 'email' => 'ada.gmail@example.com'])
        ->and($linked->json('data'))->not->toHaveKey('provider_user_id')
        // The account keeps its own email.
        ->and($this->store->run(static fn (): string => (string) Customer::query()->whereKey($ada->id)->value('email')))->toBe('ada@example.com');

    // A second Google account for Ada is refused.
    fakeSocialProvider('google', ['sub' => 'google-ada-2', 'email' => 'ada2@example.com', 'email_verified' => true, 'name' => 'Ada']);
    customerRequest('POST', 'account/identities/google', ['state' => $startLink($ada), 'code' => 'c'], $ada, 'social-store')->assertConflict()->assertJsonPath('code', 'social_provider_already_linked');

    // A sign-in's state, or another customer's connection, can't be used to connect.
    [, $signInState] = startSocialSignIn();
    customerRequest('POST', 'account/identities/google', ['state' => $signInState, 'code' => 'c'], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');
    customerRequest('POST', 'account/identities/google', ['state' => $startLink($eve), 'code' => 'c'], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');

    expect(customerRequest('GET', 'account/identities', as: $ada, subdomain: 'social-store')->json('data.*.provider'))->toBe(['google']);
});

it('disconnects a provider only while another way to sign in remains, with the password when there is one', function (): void {
    [$ada, [$google]] = customerWithIdentities(['google'], 'a-long-shopper-password');

    customerRequest('DELETE', "account/identities/{$google->public_id}", [], $ada, 'social-store')->assertUnprocessable()->assertJsonValidationErrors('current_password');
    customerRequest('DELETE', "account/identities/{$google->public_id}", ['current_password' => 'not-the-password-at-all'], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'current_password_incorrect');
    customerRequest('DELETE', "account/identities/{$google->public_id}", ['current_password' => 'a-long-shopper-password'], $ada, 'social-store')->assertNoContent();

    // Without a password, the last provider stays.
    [$eve, [$eveGoogle, $eveApple]] = customerWithIdentities(['google', 'apple'], email: 'eve@example.com');
    customerRequest('DELETE', "account/identities/{$eveGoogle->public_id}", [], $eve, 'social-store')->assertNoContent();
    customerRequest('DELETE', "account/identities/{$eveApple->public_id}", [], $eve, 'social-store')->assertConflict()->assertJsonPath('code', 'last_sign_in_method');

    // A provider the store no longer offers doesn't count as a way in.
    [$zed, [$zedGoogle]] = customerWithIdentities(['google', 'apple'], email: 'zed@example.com');
    offerSocialSignIn($this->store, ['google']);
    customerRequest('DELETE', "account/identities/{$zedGoogle->public_id}", [], $zed, 'social-store')->assertConflict();

    // Someone else's identity isn't found.
    customerRequest('DELETE', "account/identities/{$eveApple->public_id}", ['current_password' => 'a-long-shopper-password'], $ada, 'social-store')->assertNotFound();
});

it('never disconnects both of two last providers when two devices try at the same moment', function (): void {
    $this->travelBack();
    [$eve, $identities] = customerWithIdentities(['google', 'apple'], email: 'eve@example.com');

    expect(disconnectIdentitiesInProcesses($eve, $identities))->toBe(['disconnected', 'refused'])
        ->and($this->store->run(static fn (): int => CustomerIdentity::query()->count()))->toBe(1);
});

it('lets a customer who only ever signed in with a provider set a password through a password reset', function (): void {
    [$eve] = customerWithIdentities(['google'], email: 'eve@example.com');
    $token = $this->store->run(static fn (): string => Password::broker(Customer::PASSWORD_BROKER)->createToken(Customer::query()->sole()));

    customerRequest('POST', 'auth/password-resets', ['token' => $token, 'email' => 'eve@example.com', 'password' => 'a-brand-new-long-password', 'password_confirmation' => 'a-brand-new-long-password'], subdomain: 'social-store')->assertNoContent();
    customerRequest('POST', 'auth/tokens', ['email' => 'eve@example.com', 'password' => 'a-brand-new-long-password'], subdomain: 'social-store')->assertOk();
    expect($eve->id)->toBeInt();
});

it('keeps identities to the customer guard: staff tokens reach none of them', function (): void {
    $staff = $this->store->run(static fn () => App\Tenant\Identity\Models\StaffMember::factory()->create());
    $token = $this->store->run(static fn (): string => app(App\Shared\Auth\AccessTokenIssuer::class)->issue($staff, App\Tenant\Identity\Models\StaffMember::GUARD, 'test')->plainTextToken);

    customerRequest('GET', 'account/identities', as: $token, subdomain: 'social-store')->assertUnauthorized();
    customerRequest('POST', 'account/identities/google/authorizations', as: $token, subdomain: 'social-store')->assertUnauthorized();
});
