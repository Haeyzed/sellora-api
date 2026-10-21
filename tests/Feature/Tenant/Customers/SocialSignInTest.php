<?php

declare(strict_types=1);

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Events\CustomerEmailVerified;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Customers\Models\SocialSignInTicket;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 10b.3: finishing a Google sign-in. The storefront brings back the
 * state and code from the relay; the store uses the state up once (only
 * one it started), exchanges the code with its PKCE verifier, and signs in
 * the customer the provider's user ID belongs to. Never by email: an
 * account with the same email is never connected automatically, its owner
 * proves it first. A new account is verified only when the provider is
 * trusted and says the email is verified; then guest orders placed with it
 * join the account.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    Notification::fake();
    configureSocialSignInApps();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('social-store', ['country_code' => 'NG']);
    offerSocialSignIn($this->store, ['google']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('makes a verified account for a new Google user, exchanging the code with the PKCE verifier, and links their guest orders', function (): void {
    $guestOrder = $this->store->run(static fn (): string => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), buyer: OrderBuyer::guest('ada@example.com')))->public_id);
    $provider = fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'Ada@Example.com', 'email_verified' => true, 'name' => 'Ada Obi']);

    $finished = finishSocialSignIn('google', 'provider-code', ['return_path' => '/checkout'])->assertOk();

    expect($finished->json('data'))->toMatchArray(['status' => 'signed_in', 'new_account' => true, 'ticket' => null, 'proof_methods' => [], 'return_path' => '/checkout'])
        ->and($finished->json('data.token.token'))->toBeString();

    // The token exchange carried the verifier kept when the sign-in started, and the relay's address.
    $exchange = $provider->tokenRequest();
    $verifier = $this->store->run(static fn (): string => App\Tenant\Customers\Models\SocialSignInAttempt::query()->sole()->code_verifier);
    expect($exchange)->toMatchArray(['grant_type' => 'authorization_code', 'code' => 'provider-code', 'code_verifier' => $verifier, 'redirect_uri' => 'https://'.config()->array('tenancy.central_domains')[0].'/oauth/google/callback']);

    [$account, $identity, $orderCustomer] = $this->store->run(static function () use ($guestOrder): array {
        $customer = Customer::query()->sole();
        $identity = CustomerIdentity::query()->sole();

        return [
            [$customer->id, $customer->name, $customer->email, $customer->password, $customer->email_verified_at !== null],
            [$identity->provider->value, $identity->provider_user_id, $identity->customer_id],
            Order::query()->where('public_id', $guestOrder)->value('customer_id'),
        ];
    });
    [$customerId] = $account;

    expect(array_slice($account, 1))->toBe(['Ada Obi', 'ada@example.com', null, true])
        ->and($identity)->toBe(['google', 'google-ada', $customerId])
        ->and($orderCustomer)->toBe($customerId);

    // The token is a customer's, and signs them in.
    customerRequest('GET', 'auth/me', as: (string) $finished->json('data.token.token'), subdomain: 'social-store')->assertOk()->assertJsonPath('data.email', 'ada@example.com');
});

it('signs the same Google user back in by their provider ID, whatever email they now have', function (): void {
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    finishSocialSignIn('google')->assertOk();

    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada.new@example.com', 'email_verified' => true, 'name' => 'Ada']);
    $again = finishSocialSignIn('google')->assertOk();

    expect($again->json('data'))->toMatchArray(['status' => 'signed_in', 'new_account' => false])
        ->and($this->store->run(static fn (): array => [Customer::query()->count(), Customer::query()->sole()->email, CustomerIdentity::query()->sole()->email]))->toBe([1, 'ada@example.com', 'ada.new@example.com']);
});

it('never connects a provider to an existing account with the same email: its owner proves it first', function (): void {
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password', 'email_verified_at' => null]));

    fakeSocialProvider('google', ['sub' => 'google-attacker', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Not Ada']);
    $finished = finishSocialSignIn('google')->assertStatus(202);

    expect($finished->json('data'))->toMatchArray(['status' => 'link_account', 'token' => null, 'new_account' => false, 'proof_methods' => ['password', 'email_link']])
        ->and($finished->json('data.ticket'))->toBeString()
        // Nothing connected, nobody signed in, and the account still unverified.
        ->and($this->store->run(static fn (): array => [CustomerIdentity::query()->count(), Customer::query()->sole()->email_verified_at, Customer::query()->sole()->tokens()->count()]))->toBe([0, null, 0])
        ->and($this->store->run(static fn (): array => SocialSignInTicket::query()->sole()->only(['kind', 'email', 'provider_user_id'])))->toMatchArray(['email' => 'ada@example.com', 'provider_user_id' => 'google-attacker']);
});

it('makes an unverified account when the provider doesn\'t say the email is verified, so no guest orders join it', function (): void {
    $guestOrder = $this->store->run(static fn (): string => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), buyer: OrderBuyer::guest('ada@example.com')))->public_id);
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => false, 'name' => 'Ada']);
    Event::fake([CustomerEmailVerified::class]);

    finishSocialSignIn('google')->assertOk()->assertJsonPath('data.new_account', true);

    // Nothing announces the email as verified, so nothing joins the account through it.
    Event::assertNotDispatched(CustomerEmailVerified::class);

    expect($this->store->run(static fn (): array => [Customer::query()->sole()->email_verified_at, Order::query()->where('public_id', $guestOrder)->value('customer_id')]))->toBe([null, null]);
    Notification::assertSentOnDemand(App\Tenant\Customers\CustomerEmailLinkNotification::class, static fn ($notification): bool => $notification->purpose() === CustomerEmailTokenPurpose::Verification);
});

it('believes a verified email only from a trusted provider', function (): void {
    config(['customers.social.trusted_email_providers' => ['apple']]);
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);

    finishSocialSignIn('google')->assertOk();

    expect($this->store->run(static fn (): ?CarbonImmutable => Customer::query()->sole()->email_verified_at))->toBeNull();
});

it('uses a state once, only in the store that started it, for the provider and purpose it was started for', function (): void {
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    [, $state] = startSocialSignIn();

    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $state, 'code' => 'c'], subdomain: 'social-store')->assertOk();
    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $state, 'code' => 'c'], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');

    // Another store's state means nothing here, and this store's is refused there.
    $other = createStore('other-store', ['country_code' => 'NG']);
    offerSocialSignIn($other, ['google']);
    [, $theirs] = startSocialSignIn(subdomain: 'other-store');
    [, $mine] = startSocialSignIn();
    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $theirs, 'code' => 'c'], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');
    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $mine, 'code' => 'c'], subdomain: 'other-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');

    // Expired.
    $this->travel(11)->minutes();
    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $mine, 'code' => 'c'], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');
});

it('uses a state once in the store even when the storefront brings it to different servers at the same moment', function (): void {
    // The other processes keep real time.
    $this->travelBack();
    [, $state] = startSocialSignIn();

    expect(consumeSocialStateInProcesses('google', $state, 4))->toBe(['refused', 'refused', 'refused', 'used']);
});

it('refuses a deactivated account, and logs a provider\'s refusal without any code, state or token', function (): void {
    Log::spy();
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'is_active' => false]));
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    finishSocialSignIn('google')->assertForbidden();

    fakeSocialProvider('google', refuses: true);
    [, $state] = startSocialSignIn();
    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $state, 'code' => 'secret-provider-code'], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_failed');

    Log::shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => $context['provider'] === 'google' && ! str_contains((string) json_encode($context), 'secret-provider-code') && ! str_contains((string) json_encode($context), $state));
});

it('signs in customers only: staff tokens finish no sign-in, and a social token opens no staff route', function (): void {
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    $token = (string) finishSocialSignIn('google')->json('data.token.token');

    $this->withToken($token)->getJson(storeUrl('social-store', '/api/v1/staff/auth/me'))->assertUnauthorized();
    expect($this->store->run(static fn (): int => StaffMember::query()->count()))->toBe(0);
});
