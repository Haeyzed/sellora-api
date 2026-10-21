<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\SocialSignInState;
use App\Tenant\Customers\CustomerEmailLinkNotification;
use App\Tenant\Customers\Events\CustomerEmailVerified;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 10b.5: Sign in with Apple, and providers that give no email. Apple
 * posts its answer to the relay (form_post). The email is believed only from
 * Apple's signed identity token, checked against Apple's keys, the store's
 * audience and the nonce the store kept for the sign-in. The name Apple
 * posts, unsigned and only the first time, reaches the storefront in the URL
 * fragment as display text, is never stored centrally, and is checked when
 * the storefront sends it back. Private relay addresses are kept as such and
 * never match guest orders. Without an email, the customer gives one.
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

it('asks Apple to post its answer, with the nonce and no PKCE', function (): void {
    [$started, , $query] = startSocialSignIn('apple');

    expect((string) $started->json('data.authorization_url'))->toStartWith('https://appleid.apple.com/auth/authorize?')
        ->and($query)->toMatchArray(['client_id' => 'com.sellora.signin', 'response_mode' => 'form_post', 'scope' => 'name email', 'response_type' => 'code'])
        ->and($query['nonce'])->toBe($this->store->run(static fn (): string => App\Tenant\Customers\Models\SocialSignInAttempt::query()->sole()->nonce))
        ->and($query)->not->toHaveKey('code_challenge');
});

it('passes the name Apple posts on to the storefront as display text, never storing it centrally, and never Apple\'s unsigned email', function (): void {
    [, $state] = startSocialSignIn('apple');

    $relayed = relaySocialSignIn('apple', ['state' => $state, 'code' => 'apple-code', 'user' => json_encode(['name' => ['firstName' => "Ada\u{0007}", 'lastName' => 'Obi'], 'email' => 'someone.else@example.com'])], 'POST');

    expect($relayed->status())->toBe(303)
        ->and(relayedFragment($relayed))->toBe(['state' => $state, 'code' => 'apple-code', 'name' => 'Ada Obi'])
        ->and(json_encode(DB::table('social_sign_in_states')->get()))->not->toContain('Ada')->not->toContain('someone.else')
        ->and(SocialSignInState::query()->sole()->used_at)->not->toBeNull();
});

it('makes an account from Apple\'s signed identity token, with the storefront\'s name as display text only', function (): void {
    [$finished] = finishAppleSignIn('apple-ada', ['email' => 'ada@example.com', 'email_verified' => 'true', 'is_private_email' => 'false'], [
        'name' => 'Ada Obi',
        // Apple's unsigned "user" is never read from the storefront.
        'user' => json_encode(['email' => 'attacker@example.com', 'name' => ['firstName' => 'Mallory']]),
    ]);

    $finished->assertOk()->assertJsonPath('data.new_account', true);

    expect($this->store->run(static fn (): array => [
        Customer::query()->sole()->only(['name', 'email']), Customer::query()->sole()->email_verified_at !== null,
        CustomerIdentity::query()->sole()->only(['provider_user_id', 'email', 'is_private_relay']),
    ]))->toBe([['name' => 'Ada Obi', 'email' => 'ada@example.com'], true, ['provider_user_id' => 'apple-ada', 'email' => 'ada@example.com', 'is_private_relay' => false]]);
});

it('refuses an identity token not signed by Apple, for another audience, or for another sign-in\'s nonce', function (array $variant): void {
    [$finished] = finishAppleSignIn('apple-ada', ['email' => 'ada@example.com', 'email_verified' => 'true'], ...$variant);

    $finished->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_failed');
    expect($this->store->run(static fn (): int => Customer::query()->count()))->toBe(0);
})->with([
    'a forged signature' => [['forged' => true]],
    'another app\'s audience' => [['audience' => 'com.someone.else']],
    'another sign-in\'s nonce' => [['nonce' => 'not-this-sign-ins-nonce']],
]);

it('checks the display name the storefront sends back', function (string $name): void {
    [$finished] = finishAppleSignIn('apple-ada', ['email' => 'ada@example.com', 'email_verified' => 'true'], ['name' => $name]);

    $finished->assertUnprocessable()->assertJsonValidationErrors('name');
})->with([
    'markup' => ['<script>alert(1)</script>'],
    'too long' => [str_repeat('a', 121)],
    'control characters' => ["Ada\u{0000}Obi"],
]);

it('keeps a private relay address as such, and never matches guest orders with it', function (): void {
    $relay = 'x7k2@privaterelay.appleid.com';
    $guestOrder = $this->store->run(static fn (): string => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), buyer: OrderBuyer::guest($relay)))->public_id);
    Event::fake([CustomerEmailVerified::class]);

    [$finished] = finishAppleSignIn('apple-ada', ['email' => $relay, 'email_verified' => 'true', 'is_private_email' => 'true']);
    $finished->assertOk();

    Event::assertNotDispatched(CustomerEmailVerified::class);
    expect($this->store->run(static fn (): array => [CustomerIdentity::query()->sole()->is_private_relay, Order::query()->where('public_id', $guestOrder)->value('customer_id')]))->toBe([true, null]);
});

it('asks for an email when the provider gives none, and makes the account unverified with it', function (): void {
    [$finished] = finishAppleSignIn('apple-ada', []);
    $finished->assertStatus(202)->assertJsonPath('data.status', 'email_required');
    $ticket = (string) $finished->json('data.ticket');

    $completed = customerRequest('POST', 'auth/social/email-completions', ['ticket' => $ticket, 'email' => 'Ada@Example.com'], subdomain: 'social-store')->assertOk();

    expect($completed->json('data'))->toMatchArray(['status' => 'signed_in', 'new_account' => true])
        ->and($this->store->run(static fn (): array => [Customer::query()->sole()->email, Customer::query()->sole()->email_verified_at, CustomerIdentity::query()->sole()->provider_user_id]))->toBe(['ada@example.com', null, 'apple-ada']);
    Notification::assertSentOnDemand(CustomerEmailLinkNotification::class);

    // A ticket works once.
    customerRequest('POST', 'auth/social/email-completions', ['ticket' => $ticket, 'email' => 'eve@example.com'], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');
});

it('never connects to an existing account through an email the customer typed: only its password proves it', function (): void {
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password']));
    [$finished] = finishAppleSignIn('apple-mallory', []);

    $completed = customerRequest('POST', 'auth/social/email-completions', ['ticket' => (string) $finished->json('data.ticket'), 'email' => 'ada@example.com'], subdomain: 'social-store')->assertStatus(202);

    expect($completed->json('data'))->toMatchArray(['status' => 'link_account', 'proof_methods' => ['password']])
        ->and($this->store->run(static fn (): int => CustomerIdentity::query()->count()))->toBe(0);
    customerRequest('POST', 'auth/social/link-emails', ['ticket' => (string) $completed->json('data.ticket')], subdomain: 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_link_email_not_allowed');
});
