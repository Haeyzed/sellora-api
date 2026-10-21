<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\SocialSignInState;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Customers\Models\SocialSignInAttempt;
use App\Tenant\Customers\Models\SocialSignInTicket;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 10b.6: customer social sign-in and privacy, limits and logs. A
 * customer's identities are in their data export (never the provider's ID
 * for them) and go with erasure, with any sign-in in progress for them.
 * Sign-ins started, tickets and the relay's routing are purged once expired.
 * Starting sign-ins is limited per address in each store, without one store
 * using up another's; emailed links per ticket. No code, state, ticket or
 * token is ever logged.
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

it('puts a customer\'s sign-in providers in their data export, without the provider\'s ID for them', function (): void {
    [$ada] = customerWithIdentities(['google', 'apple'], 'a-long-shopper-password');

    $export = $this->store->run(static fn (): array => app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($ada)));

    expect(array_column($export['social_identities'], 'provider'))->toBe(['google', 'apple'])
        ->and($export['social_identities'][0])->toHaveKeys(['provider', 'email', 'is_private_relay', 'connected_at', 'last_used_at'])
        ->and(json_encode($export))->not->toContain('google-'.$ada->id);
});

it('removes a customer\'s identities, and any sign-in in progress for them, on erasure; the provider then signs in someone new', function (): void {
    [$ada] = customerWithIdentities(['google'], 'a-long-shopper-password');
    $this->store->run(static function () use ($ada): void {
        (new SocialSignInAttempt)->forceFill([
            'state_hash' => hash('sha256', 'x'), 'provider' => 'google', 'intent' => 'link', 'customer_id' => $ada->id, 'code_verifier' => 'v', 'nonce' => 'n',
            'expires_at' => CarbonImmutable::now()->addMinutes(10), 'created_at' => CarbonImmutable::now(),
        ])->save();
        (new SocialSignInTicket)->forceFill([
            'token_hash' => hash('sha256', 'y'), 'kind' => 'link_account', 'provider' => 'apple', 'provider_user_id' => 'apple-x', 'email' => 'ada@example.com',
            'email_verified' => true, 'customer_id' => $ada->id, 'expires_at' => CarbonImmutable::now()->addMinutes(10), 'created_at' => CarbonImmutable::now(),
        ])->save();

        app(EraseCustomer::class)->erase($ada);
    });

    expect($this->store->run(static fn (): array => [CustomerIdentity::query()->count(), SocialSignInAttempt::query()->count(), SocialSignInTicket::query()->count()]))->toBe([0, 0, 0]);

    fakeSocialProvider('google', ['sub' => 'google-'.$ada->id, 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    finishSocialSignIn('google')->assertOk()->assertJsonPath('data.new_account', true);
    expect($this->store->run(static fn (): int => Customer::withTrashed()->whereNotNull('erased_at')->count()))->toBe(1);
});

it('purges sign-ins, tickets and the relay\'s routing once they have expired', function (): void {
    [, $state] = startSocialSignIn();
    fakeSocialProvider('google', ['sub' => 'google-x', 'email' => 'x@example.com', 'email_verified' => true, 'name' => 'X']);
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'x@example.com']));
    finishSocialSignIn('google')->assertStatus(202);

    $this->travel(2)->days();
    PurgeExpiredRecords::dispatchSync();
    $this->store->run(static fn () => PurgeExpiredRecords::dispatchSync());

    expect(SocialSignInState::query()->count())->toBe(0)
        ->and($this->store->run(static fn (): array => [SocialSignInAttempt::query()->count(), SocialSignInTicket::query()->count()]))->toBe([0, 0])
        ->and($state)->not->toBe('');
});

it('limits starting sign-ins per address in each store, never using up another store\'s', function (): void {
    config(['api.rate_limits.social_sign_in_ip_ceiling' => 2]);
    $other = createStore('other-store', ['country_code' => 'NG']);
    offerSocialSignIn($other, ['google']);

    startSocialSignIn()[0]->assertCreated();
    startSocialSignIn()[0]->assertCreated();
    startSocialSignIn()[0]->assertTooManyRequests();

    startSocialSignIn(subdomain: 'other-store')[0]->assertCreated();
});

it('limits links emailed for one ticket', function (): void {
    config(['api.rate_limits.account_emails' => 2]);
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password']));
    [$ticket] = linkTicketFor(['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);

    customerRequest('POST', 'auth/social/link-emails', ['ticket' => $ticket], subdomain: 'social-store')->assertAccepted();
    customerRequest('POST', 'auth/social/link-emails', ['ticket' => $ticket], subdomain: 'social-store')->assertAccepted();
    customerRequest('POST', 'auth/social/link-emails', ['ticket' => $ticket], subdomain: 'social-store')->assertTooManyRequests();
});

it('never logs a code, state, ticket or token, whatever happens', function (): void {
    $logged = [];
    Log::listen(static function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message->message.' '.json_encode($message->context);
    });
    $secrets = [];

    [, $state] = startSocialSignIn();
    $secrets[] = $state;
    relaySocialSignIn('google', ['state' => $state, 'code' => 'secret-code-one']);
    fakeSocialProvider('google', refuses: true);
    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $state, 'code' => 'secret-code-one'], subdomain: 'social-store');

    $this->store->run(static fn () => Customer::factory()->create(['email' => 'ada@example.com', 'password' => 'a-long-shopper-password']));
    fakeSocialProvider('google', ['sub' => 'google-ada', 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    $answer = finishSocialSignIn('google', 'secret-code-two');
    $secrets[] = (string) $answer->json('data.ticket');
    $signedIn = customerRequest('POST', 'auth/social/link-confirmations', ['ticket' => (string) $answer->json('data.ticket'), 'password' => 'a-long-shopper-password'], subdomain: 'social-store');
    $secrets[] = (string) $signedIn->json('data.token.token');
    $secrets[] = 'secret-code-one';
    $secrets[] = 'secret-code-two';
    $secrets[] = 'provider-access-token';

    expect(array_filter($secrets))->toHaveCount(6)
        // The provider's refusal was logged, by kind only.
        ->and(implode("\n", $logged))->toContain('social sign-in provider');

    foreach ($logged as $line) {
        foreach ($secrets as $secret) {
            expect($line)->not->toContain($secret);
        }
    }
});

it('rejects other guards\' tokens and other stores\' customers on every customer social route', function (): void {
    [$ada] = customerWithIdentities(['google'], 'a-long-shopper-password');
    $other = createStore('other-store', ['country_code' => 'NG']);
    $theirs = $other->run(static fn (): Customer => Customer::factory()->create());
    $theirToken = customerTokenFor($theirs, 'other-store');

    // Another store's customer token means nothing here.
    customerRequest('GET', 'account/identities', as: $theirToken, subdomain: 'social-store')->assertUnauthorized();

    // A driver's token neither.
    $driverToken = $this->store->run(static function (): string {
        $driver = App\Tenant\Delivery\Models\Driver::factory()->create();

        return app(App\Shared\Auth\AccessTokenIssuer::class)->issue($driver, App\Tenant\Delivery\Models\Driver::GUARD, 'test')->plainTextToken;
    });
    customerRequest('GET', 'account/identities', as: $driverToken, subdomain: 'social-store')->assertUnauthorized();

    expect(customerRequest('GET', 'account/identities', as: $ada, subdomain: 'social-store')->assertOk()->json('data.*.provider'))->toBe(['google']);
});
