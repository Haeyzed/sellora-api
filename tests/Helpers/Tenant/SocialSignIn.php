<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Customers\FakeSocialProviderHttp;

/*
|--------------------------------------------------------------------------
| For the customer social sign-in tests (Step 10b)
|--------------------------------------------------------------------------
*/

/**
 * Gives the platform its own Google and Apple sign-in apps, as the environment would.
 */
function configureSocialSignInApps(): void
{
    config([
        'services.google.client_id' => 'platform-google-app',
        'services.google.client_secret' => 'platform-google-secret',
        'services.apple.client_id' => 'com.sellora.signin',
        'services.apple.client_secret' => 'platform-apple-secret',
        'customers.social.relay_url' => 'https://'.config()->array('tenancy.central_domains')[0].'/oauth/{provider}/callback',
    ]);
}

/**
 * Makes the store offer these providers to its customers.
 *
 * @param  list<string>  $providers
 */
function offerSocialSignIn(Tenant $store, array $providers): void
{
    $store->run(static fn () => StoreSettings::query()->sole()->update(['social_sign_in_providers' => $providers]));
}

/**
 * Starts a social sign-in at a store's storefront and returns the response, with the one-time state and the query of the provider's address it returned (empty when it didn't start).
 *
 * @param  array<string, mixed>  $data
 * @return array{TestResponse, string, array<string, string>}
 */
function startSocialSignIn(string $provider = 'google', array $data = [], string $subdomain = 'social-store'): array
{
    $response = customerRequest('POST', "auth/social/{$provider}/authorizations", $data, subdomain: $subdomain);
    $url = $response->json('data.authorization_url');
    $query = [];

    if (is_string($url)) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    }

    /** @var array<string, string> $query */
    return [$response, (string) ($query['state'] ?? ''), $query];
}

/**
 * Makes the provider's servers fake ones for the next sign-in: they accept the code and say this about the person (Google's userinfo claims), or refuse it.
 *
 * @param  array<string, mixed>  $person
 * @param  array<string, mixed>  $tokenResponse
 */
function fakeSocialProvider(string $provider, array $person = [], bool $refuses = false, array $tokenResponse = []): FakeSocialProviderHttp
{
    $http = new FakeSocialProviderHttp($person, $refuses, $tokenResponse);

    $socialite = app(Socialite::class);

    if (! $socialite instanceof SocialiteManager) {
        throw new LogicException('Socialite is not its usual manager.');
    }

    $socialite->extend($provider, static function () use ($provider, $http): AbstractProvider {
        $class = match ($provider) {
            'google' => GoogleProvider::class,
            default => throw new InvalidArgumentException("No fake for {$provider}."),
        };
        $driver = new $class(request(), config()->string("services.{$provider}.client_id"), config()->string("services.{$provider}.client_secret"), config()->string("services.{$provider}.redirect"));
        $driver->setHttpClient($http->client);

        return $driver;
    });

    return $http;
}

/**
 * Starts a social sign-in at the store, then finishes it as the storefront's callback page would, with the code from the relay.
 *
 * @param  array<string, mixed>  $startData
 * @param  array<string, mixed>  $finishData
 */
function finishSocialSignIn(string $provider = 'google', string $code = 'provider-code', array $startData = [], string $subdomain = 'social-store', array $finishData = []): TestResponse
{
    [$started, $state] = startSocialSignIn($provider, $startData, $subdomain);
    $started->assertCreated();

    return customerRequest('POST', "auth/social/{$provider}/callbacks", ['state' => $state, 'code' => $code, ...$finishData], subdomain: $subdomain);
}

/**
 * Brings a provider's answer to the central relay page.
 *
 * @param  array<string, string>  $parameters
 * @param  array<string, string>  $headers
 */
function relaySocialSignIn(string $provider, array $parameters, string $method = 'GET', array $headers = []): TestResponse
{
    $server = [];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call($method, centralUrl("/oauth/{$provider}/callback"), $parameters, [], [], $server);
}

/**
 * The fragment of the relay's redirect, decoded: what the storefront's callback page reads.
 *
 * @return array<string, string>
 */
function relayedFragment(TestResponse $response): array
{
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_FRAGMENT), $fragment);

    /** @var array<string, string> $fragment */
    return $fragment;
}

/**
 * Signs in with Google as this person, who has the email of an existing account, and returns the ticket to prove ownership and how it may be proved.
 *
 * @param  array<string, mixed>  $person
 * @return array{string, list<string>}
 */
function linkTicketFor(array $person): array
{
    fakeSocialProvider('google', $person);
    $answer = finishSocialSignIn('google')->assertStatus(202)->assertJsonPath('data.status', 'link_account');

    /** @var list<string> $proofs */
    $proofs = $answer->json('data.proof_methods');

    return [(string) $answer->json('data.ticket'), $proofs];
}

/**
 * A customer of the test store with an identity for each provider given, made directly; no password unless one is given.
 *
 * @param  list<string>  $providers
 * @return array{Customer, list<CustomerIdentity>}
 */
function customerWithIdentities(array $providers, ?string $password = null, string $email = 'ada@example.com'): array
{
    return test()->store->run(static function () use ($providers, $password, $email): array {
        $customer = Customer::factory()->create(['email' => $email, 'password' => $password]);
        $identities = [];

        foreach ($providers as $provider) {
            $identity = new CustomerIdentity;
            $identity->forceFill([
                'customer_id' => $customer->id, 'provider' => $provider, 'provider_user_id' => "{$provider}-{$customer->id}", 'email' => $email,
                'email_verified' => true, 'created_at' => CarbonImmutable::now(),
            ])->save();
            $identities[] = $identity;
        }

        return [$customer, $identities];
    });
}

/**
 * Disconnects each identity from several PHP processes at the same moment, as two devices would, and returns what each did: "disconnected" or "refused".
 *
 * @param  list<CustomerIdentity>  $identities
 * @return list<string>
 */
function disconnectIdentitiesInProcesses(Customer $customer, array $identities): array
{
    $startAt = (string) (microtime(true) + 2.0);
    $running = [];

    foreach ($identities as $identity) {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/Customers/disconnect-customer-identity.php'), DB::connection()->getDatabaseName(), (string) test()->store->getTenantKey(), (string) $customer->id, (string) $identity->id, $startAt]);
        $process->setTimeout(60);
        $process->start();
        $running[] = $process;
    }

    $answers = [];

    foreach ($running as $process) {
        $process->wait();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('A process disconnecting an identity failed: '.$process->getErrorOutput().$process->getOutput());
        }

        $answers[] = trim($process->getOutput());
    }

    sort($answers);

    return $answers;
}

/**
 * Uses up the same state in the test store from several PHP processes at the same moment, as the storefront's callback reaching different servers would, and returns what each did: "used" or "refused".
 *
 * @return list<string>
 */
function consumeSocialStateInProcesses(string $provider, string $state, int $processes): array
{
    $startAt = (string) (microtime(true) + 2.0);
    $running = [];

    for ($started = 0; $started < $processes; $started++) {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/Customers/consume-social-sign-in-attempt.php'), DB::connection()->getDatabaseName(), (string) test()->store->getTenantKey(), $provider, $state, $startAt]);
        $process->setTimeout(60);
        $process->start();
        $running[] = $process;
    }

    $answers = [];

    foreach ($running as $process) {
        $process->wait();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('A process using a sign-in state failed: '.$process->getErrorOutput().$process->getOutput());
        }

        $answers[] = trim($process->getOutput());
    }

    sort($answers);

    return $answers;
}

/**
 * Brings the same state to the relay from several PHP processes at the same moment, as browsers on different servers would, and returns what each was answered: the store's domain, or "none".
 *
 * @return list<string>
 */
function relaySocialStateInProcesses(string $provider, string $state, int $processes): array
{
    $startAt = (string) (microtime(true) + 1.5);
    $running = [];

    for ($started = 0; $started < $processes; $started++) {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/Customers/claim-social-sign-in-state.php'), DB::connection()->getDatabaseName(), $provider, $state, $startAt]);
        $process->setTimeout(60);
        $process->start();
        $running[] = $process;
    }

    $answers = [];

    foreach ($running as $process) {
        $process->wait();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('A process claiming a sign-in state failed: '.$process->getErrorOutput().$process->getOutput());
        }

        $answers[] = trim($process->getOutput());
    }

    sort($answers);

    return $answers;
}
