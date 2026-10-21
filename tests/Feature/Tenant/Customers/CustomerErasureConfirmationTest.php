<?php

declare(strict_types=1);

use App\Tenant\Customers\CustomerEmailLinkNotification;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Models\CustomerEmailToken;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Customers\Models\SocialSignInAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Asking for erasure is never harder than signing in (section 3.3). A
 * customer with a password confirms with it. One without confirms with a
 * one-time token: from a link emailed to their account address, or after
 * signing in again with a connected provider. Being signed in alone, as with
 * a stolen access token, is never enough.
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

it('keeps confirming erasure with the password for customers who have one, and offers them nothing else', function (): void {
    [$ada] = customerWithIdentities(['google'], 'a-long-shopper-password');

    customerRequest('POST', 'account/erasure-emails', as: $ada, subdomain: 'social-store')->assertConflict()->assertJsonPath('code', 'erasure_confirmed_by_password');
    customerRequest('POST', 'account/erasure-reauthentications/google/authorizations', as: $ada, subdomain: 'social-store')->assertConflict();
    customerRequest('DELETE', 'account', ['erasure_token' => 'anything'], $ada, 'social-store')->assertUnprocessable()->assertJsonValidationErrors(['current_password', 'erasure_token']);
    Notification::assertNothingSent();

    customerRequest('DELETE', 'account', ['current_password' => 'a-long-shopper-password'], $ada, 'social-store')->assertOk();
    expect(isErased($ada))->toBeTrue();
});

it('erases a customer without a password with the link emailed to their account address, used while signed in', function (): void {
    [$ada] = customerWithIdentities(['google']);

    customerRequest('POST', 'account/erasure-emails', as: $ada, subdomain: 'social-store')->assertAccepted();
    $token = emailedCustomerToken(CustomerEmailTokenPurpose::Erasure, 'ada@example.com');
    expect($this->store->run(static fn (): string => CustomerEmailToken::query()->sole()->token_hash))->toBe(CustomerEmailToken::hash($token));
    Notification::assertSentOnDemand(CustomerEmailLinkNotification::class, static fn (CustomerEmailLinkNotification $notification): bool => str_starts_with($notification->url(), 'https://social-store.'));

    customerRequest('DELETE', 'account', as: $ada, subdomain: 'social-store')->assertUnprocessable()->assertJsonValidationErrors('erasure_token');
    customerRequest('DELETE', 'account', ['erasure_token' => $token], $ada, 'social-store')->assertOk();

    expect(isErased($ada))->toBeTrue()
        ->and($this->store->run(static fn (): int => CustomerEmailToken::query()->count()))->toBe(0);
});

it('takes an emailed erasure link only while it is the customer\'s latest, unexpired one', function (): void {
    [$ada] = customerWithIdentities(['google']);

    customerRequest('POST', 'account/erasure-emails', as: $ada, subdomain: 'social-store')->assertAccepted();
    $first = emailedCustomerToken(CustomerEmailTokenPurpose::Erasure, 'ada@example.com');
    customerRequest('POST', 'account/erasure-emails', as: $ada, subdomain: 'social-store')->assertAccepted();
    $second = emailedCustomerToken(CustomerEmailTokenPurpose::Erasure, 'ada@example.com');

    customerRequest('DELETE', 'account', ['erasure_token' => $first], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'erasure_confirmation_invalid');

    $this->travel(16)->minutes();
    customerRequest('DELETE', 'account', ['erasure_token' => $second], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'erasure_confirmation_invalid');
    expect(isErased($ada))->toBeFalse();
});

it('never takes another customer\'s erasure link', function (): void {
    [$ada] = customerWithIdentities(['google']);
    [$grace] = customerWithIdentities(['apple'], email: 'grace@example.com');

    customerRequest('POST', 'account/erasure-emails', as: $grace, subdomain: 'social-store')->assertAccepted();
    $graces = emailedCustomerToken(CustomerEmailTokenPurpose::Erasure, 'grace@example.com');

    customerRequest('DELETE', 'account', ['erasure_token' => $graces], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'erasure_confirmation_invalid');
    expect(isErased($ada))->toBeFalse()->and(isErased($grace))->toBeFalse();
});

it('erases a customer without a password after a fresh sign-in with a connected provider, within minutes', function (): void {
    [$ada] = customerWithIdentities(['google']);

    [$started, $state] = startErasureReauthentication($ada);
    $started->assertCreated();
    fakeSocialProvider('google', ['sub' => 'google-'.$ada->id, 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    $token = (string) customerRequest('POST', 'account/erasure-reauthentications/google', ['state' => $state, 'code' => 'provider-code'], $ada, 'social-store')
        ->assertCreated()->assertJsonPath('data.expires_at', CarbonImmutable::now()->addMinutes(5)->toIso8601String())->json('data.erasure_token');

    customerRequest('DELETE', 'account', ['erasure_token' => $token], $ada, 'social-store')->assertOk();
    expect(isErased($ada))->toBeTrue();
});

it('lets the token from a fresh sign-in expire after a few minutes', function (): void {
    [$ada] = customerWithIdentities(['google']);

    [, $state] = startErasureReauthentication($ada);
    fakeSocialProvider('google', ['sub' => 'google-'.$ada->id, 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);
    $token = (string) customerRequest('POST', 'account/erasure-reauthentications/google', ['state' => $state, 'code' => 'provider-code'], $ada, 'social-store')->json('data.erasure_token');

    $this->travel(6)->minutes();
    customerRequest('DELETE', 'account', ['erasure_token' => $token], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'erasure_confirmation_invalid');
    expect(isErased($ada))->toBeFalse();
});

it('never lets a stolen access token erase an account on its own', function (): void {
    [$ada] = customerWithIdentities(['google']);
    $stolen = customerTokenFor($ada, 'social-store');

    // No confirmation, or a made-up one.
    customerRequest('DELETE', 'account', as: $stolen, subdomain: 'social-store')->assertUnprocessable();
    customerRequest('DELETE', 'account', ['erasure_token' => str_repeat('a', 64)], $stolen, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'erasure_confirmation_invalid');

    // Asking for the email sends it to the account's own address, never one the thief gives.
    customerRequest('POST', 'account/erasure-emails', ['email' => 'thief@example.com'], $stolen, 'social-store')->assertAccepted();
    Notification::assertSentOnDemandTimes(CustomerEmailLinkNotification::class, 1);
    Notification::assertSentOnDemand(CustomerEmailLinkNotification::class, static fn ($notification, array $channels, $notifiable): bool => $notifiable->routeNotificationFor('mail') === 'ada@example.com');

    // Signing in again with the thief's own provider account proves nothing.
    [, $state] = startErasureReauthentication($stolen);
    fakeSocialProvider('google', ['sub' => 'google-thief', 'email' => 'thief@example.com', 'email_verified' => true, 'name' => 'Thief']);
    customerRequest('POST', 'account/erasure-reauthentications/google', ['state' => $state, 'code' => 'provider-code'], $stolen, 'social-store')
        ->assertForbidden()->assertJsonPath('code', 'social_identity_not_connected');

    expect(isErased($ada))->toBeFalse()
        ->and($this->store->run(static fn (): int => CustomerEmailToken::query()->where('purpose', 'erasure')->count()))->toBe(1);
});

it('keeps sign-ins started for erasure, linking and signing in apart', function (): void {
    [$ada] = customerWithIdentities(['google']);
    fakeSocialProvider('google', ['sub' => 'google-'.$ada->id, 'email' => 'ada@example.com', 'email_verified' => true, 'name' => 'Ada']);

    // A sign-in Ada started to erase can't link a provider or sign anyone in...
    [, $state] = startErasureReauthentication($ada);
    customerRequest('POST', 'account/identities/google', ['state' => $state, 'code' => 'provider-code'], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');
    [, $state] = startErasureReauthentication($ada);
    customerRequest('POST', 'auth/social/google/callbacks', ['state' => $state, 'code' => 'provider-code'], subdomain: 'social-store')->assertUnprocessable();

    // ...a sign-in she started to link a provider can't confirm erasure...
    $state = linkingState($ada);
    customerRequest('POST', 'account/erasure-reauthentications/google', ['state' => $state, 'code' => 'provider-code'], $ada, 'social-store')->assertUnprocessable()->assertJsonPath('code', 'social_sign_in_invalid');

    // ...and neither can a sign-in, or another customer's sign-in to erase.
    [, $state] = startSocialSignIn();
    customerRequest('POST', 'account/erasure-reauthentications/google', ['state' => $state, 'code' => 'provider-code'], $ada, 'social-store')->assertUnprocessable();
    [$grace] = customerWithIdentities(['google'], email: 'grace@example.com');
    [, $state] = startErasureReauthentication($grace);
    customerRequest('POST', 'account/erasure-reauthentications/google', ['state' => $state, 'code' => 'provider-code'], $ada, 'social-store')->assertUnprocessable();

    expect($this->store->run(static fn (): array => SocialSignInAttempt::query()->where('intent', 'reauthenticate')->pluck('customer_id')->unique()->sort()->values()->all()))->toBe([$ada->id, $grace->id])
        ->and($this->store->run(static fn (): int => CustomerIdentity::query()->count()))->toBe(2)
        ->and($this->store->run(static fn (): int => CustomerEmailToken::query()->count()))->toBe(0);
});
