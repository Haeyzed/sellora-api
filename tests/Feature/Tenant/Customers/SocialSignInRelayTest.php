<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\SocialSignInState;
use App\Tenant\Customers\Models\SocialSignInAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 10b.2: starting a customer social sign-in, and the central relay page.
 * One platform-owned app per provider answers to the relay, which sends the
 * browser on only to the store bound to the one-time state (hashed, used
 * once, short-lived), never to an address from the request, with no referrer
 * and nothing cached. The store keeps everything about the sign-in; the
 * platform keeps only where to send it.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    configureSocialSignInApps();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('social-store', ['country_code' => 'NG']);
    offerSocialSignIn($this->store, ['google']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('starts a sign-in with PKCE, keeping the details in the store and only the routing centrally', function (): void {
    [$response, $state, $query] = startSocialSignIn(data: ['return_path' => '/checkout?step=2']);
    $response->assertCreated();

    expect((string) $response->json('data.authorization_url'))->toStartWith('https://accounts.google.com/o/oauth2/auth?')
        ->and($query)->toMatchArray([
            'client_id' => 'platform-google-app',
            'redirect_uri' => 'https://'.config()->array('tenancy.central_domains')[0].'/oauth/google/callback',
            'response_type' => 'code',
            'code_challenge_method' => 'S256',
        ])
        ->and(strlen($state))->toBe(64)
        ->and($query['code_challenge'])->toMatch('/^[A-Za-z0-9_-]{43}$/');

    $attempt = $this->store->run(static fn (): SocialSignInAttempt => SocialSignInAttempt::query()->sole());

    // The store keeps the verifier (encrypted), the nonce and the return path, keyed by the state's hash; never the state.
    expect($attempt->state_hash)->toBe(hash('sha256', $state))
        ->and(rtrim(strtr(base64_encode(hash('sha256', $attempt->code_verifier, true)), '+/', '-_'), '='))->toBe($query['code_challenge'])
        ->and($attempt->nonce)->toBe($query['nonce'])
        ->and([$attempt->intent->value, $attempt->customer_id, $attempt->return_path])->toBe(['sign_in', null, '/checkout?step=2'])
        ->and($this->store->run(static fn (): string => (string) DB::table('social_sign_in_attempts')->value('code_verifier')))->not->toBe($attempt->code_verifier);

    // The platform keeps only where to send it.
    expect(SocialSignInState::query()->sole()->only(['state_hash', 'tenant_id', 'provider']))->toBe(['state_hash' => hash('sha256', $state), 'tenant_id' => $this->store->getTenantKey(), 'provider' => 'google'])
        ->and(json_encode(DB::table('social_sign_in_states')->get()))->not->toContain($state)->not->toContain($attempt->nonce)->not->toContain('/checkout');
});

it('starts only with a provider the store offers, and returns only to a path in the storefront', function (): void {
    startSocialSignIn('apple')[0]->assertUnprocessable()->assertJsonPath('code', 'social_provider_not_offered');
    startSocialSignIn('facebook')[0]->assertNotFound();

    foreach (['//evil.example/x', '/\\evil.example', 'https://evil.example/', 'javascript:alert(1)', 'checkout', '/a b'] as $path) {
        startSocialSignIn(data: ['return_path' => $path])[0]->assertUnprocessable()->assertJsonValidationErrors('return_path');
    }

    expect(customerRequest('GET', 'auth/social/providers', subdomain: 'social-store')->assertOk()->json('data'))->toBe([['provider' => 'google']]);

    // The platform has no Apple app here: a store choosing it offers nothing for it.
    config(['services.apple.client_id' => null]);
    offerSocialSignIn($this->store, ['google', 'apple']);
    expect(customerRequest('GET', 'auth/social/providers', subdomain: 'social-store')->json('data'))->toBe([['provider' => 'google']]);
    startSocialSignIn('apple')[0]->assertUnprocessable();
});

it('relays the answer once, only to the store bound to the state, with no referrer and nothing cached', function (): void {
    [, $state] = startSocialSignIn();

    $relayed = relaySocialSignIn('google', ['state' => $state, 'code' => 'provider-code']);

    expect($relayed->status())->toBe(303)
        ->and($relayed->headers->get('Location'))->toBe('https://social-store.'.config('platform.domain').'/auth/social/callback#state='.$state.'&code=provider-code')
        ->and($relayed->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($relayed->headers->get('Cache-Control'))->toContain('no-store');

    // Used once.
    $again = relaySocialSignIn('google', ['state' => $state, 'code' => 'provider-code']);
    expect([$again->status(), $again->headers->has('Location'), $again->headers->get('Referrer-Policy'), $again->headers->get('Cache-Control')])->toBe([400, false, 'no-referrer', 'no-store, private']);
});

it('refuses a tampered, expired or other provider\'s state, and redirects nowhere', function (): void {
    [, $state] = startSocialSignIn();

    foreach ([
        ['google', $state.'x'],
        ['google', ''],
        ['apple', $state],
        ['google', str_repeat('a', 500)],
    ] as [$provider, $tampered]) {
        $refused = relaySocialSignIn($provider, ['state' => $tampered, 'code' => 'provider-code']);
        expect([$refused->status(), $refused->headers->has('Location')])->toBe([400, false]);
    }

    // Untouched by those, it still works until it expires.
    $this->travel(11)->minutes();
    $expired = relaySocialSignIn('google', ['state' => $state, 'code' => 'provider-code']);
    expect([$expired->status(), $expired->headers->has('Location')])->toBe([400, false]);
});

it('ignores any address in the request: redirect targets in the query and forged host headers change nothing', function (): void {
    [, $state] = startSocialSignIn();

    $relayed = relaySocialSignIn('google', [
        'state' => $state, 'code' => 'provider-code',
        'redirect_uri' => 'https://evil.example/steal', 'redirect' => 'https://evil.example', 'return_to' => '//evil.example', 'next' => 'https://evil.example', 'domain' => 'evil.example',
    ], headers: ['X-Forwarded-Host' => 'evil.example', 'Referer' => 'https://evil.example/']);

    expect($relayed->headers->get('Location'))->toBe('https://social-store.'.config('platform.domain').'/auth/social/callback#state='.$state.'&code=provider-code')
        ->and((string) $relayed->headers->get('Location'))->not->toContain('evil');
});

it('sends each store\'s answers to that store only', function (): void {
    $other = createStore('other-store', ['country_code' => 'NG']);
    offerSocialSignIn($other, ['google']);
    [, $mine] = startSocialSignIn();
    [, $theirs] = startSocialSignIn(subdomain: 'other-store');

    expect(parse_url((string) relaySocialSignIn('google', ['state' => $theirs, 'code' => 'c'])->headers->get('Location'), PHP_URL_HOST))->toBe('other-store.'.config('platform.domain'))
        ->and(parse_url((string) relaySocialSignIn('google', ['state' => $mine, 'code' => 'c'])->headers->get('Location'), PHP_URL_HOST))->toBe('social-store.'.config('platform.domain'));
});

it('relays nothing for a store that no longer serves requests', function (): void {
    [, $state] = startSocialSignIn();
    $this->store->forceFill(['status' => TenantStatus::ProvisioningFailed])->save();

    expect(relaySocialSignIn('google', ['state' => $state, 'code' => 'c'])->status())->toBe(400);
});

it('passes the provider\'s refusal on to the store as a plain code, and escapes any provider text it shows', function (): void {
    [, $state] = startSocialSignIn();

    expect(relayedFragment(relaySocialSignIn('google', ['state' => $state, 'error' => 'access_denied', 'code' => 'ignored'])))->toBe(['state' => $state, 'error' => 'access_denied']);

    [, $second] = startSocialSignIn();
    expect(relayedFragment(relaySocialSignIn('google', ['state' => $second, 'error' => '<script>alert(1)</script>'])))->toBe(['state' => $second, 'error' => 'provider_error']);

    $page = relaySocialSignIn('google', ['state' => 'unknown', 'error' => '<script>alert(1)</script>']);
    expect($page->getContent())->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>');
});

it('relays a state once even when browsers bring it to different servers at the same moment', function (): void {
    // The other processes keep real time.
    $this->travelBack();
    [, $state] = startSocialSignIn();

    expect(relaySocialStateInProcesses('google', $state, 4))->toBe(['none', 'none', 'none', 'social-store.'.config('platform.domain')]);
});

it('answers the relay only on the central domain', function (): void {
    [, $state] = startSocialSignIn();

    $this->get(storeUrl('social-store', "/oauth/google/callback?state={$state}&code=c"))->assertNotFound();
});
