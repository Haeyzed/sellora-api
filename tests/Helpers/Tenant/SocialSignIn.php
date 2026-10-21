<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\Process;

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
