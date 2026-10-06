<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full application. Architecture and unit tests run
| without it. Module and integration test folders are added here as each
| module or integration is created.
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Store helpers
|--------------------------------------------------------------------------
|
| Tests that create stores provision real tenant databases, so they use
| DatabaseTruncation (PostgreSQL can't create a database inside a
| transaction) and delete their stores afterwards, which drops the databases.
|
*/

/**
 * Creates a store with its own database, reachable on "<subdomain>.<platform domain>".
 */
function createStore(string $subdomain): Tenant
{
    $store = Tenant::query()->create();
    $store->domains()->create(['domain' => $subdomain.'.'.config('platform.domain')]);

    return $store;
}

/**
 * Creates only a store's row in the central database, without provisioning its database, for tests of platform-side logic.
 */
function createStoreRecord(): Tenant
{
    return Tenant::withoutEvents(static fn (): Tenant => Tenant::query()->create(['id' => (string) Str::uuid()]));
}

/**
 * Forgets who is signed in, as a real new request would. Laravel's test client
 * otherwise keeps a guard's user between requests, which hides revoked tokens.
 */
function forgetSignIns(): void
{
    app('auth')->forgetGuards();
}

/*
|--------------------------------------------------------------------------
| Two-factor helpers
|--------------------------------------------------------------------------
*/

/**
 * Turns two-factor authentication on for an account, as a confirmed setup would, and returns the authenticator secret.
 */
function enableTwoFactor(Model&TwoFactorAuthenticatable $account, string ...$recoveryCodes): string
{
    $twoFactorAuthenticator = app(TwoFactorAuthenticator::class);
    $secret = $twoFactorAuthenticator->newSecret();

    $account->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => array_map($twoFactorAuthenticator->hashRecoveryCode(...), array_values($recoveryCodes)),
        'two_factor_last_used_timestep' => null,
    ])->save();

    return $secret;
}

/**
 * The code an authenticator app shows for a secret, now or a number of 30-second steps away.
 */
function twoFactorCode(string $secret, int $stepsFromNow = 0): string
{
    $google2fa = new Google2FA;

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $stepsFromNow);
}

/**
 * The full URL of a path on the platform's central domain. Needed after a store request, because the test client keeps the last request's domain for relative paths.
 */
function centralUrl(string $path): string
{
    return 'http://'.config()->array('tenancy.central_domains')[0].$path;
}

/**
 * The full URL of a path on a store's domain.
 */
function storeUrl(string $subdomain, string $path): string
{
    return 'http://'.$subdomain.'.'.config('platform.domain').$path;
}

/**
 * Leaves the current store and deletes every store created by the test, dropping their databases.
 */
function deleteAllStores(): void
{
    tenancy()->end();

    Tenant::query()->get()->each(static fn (Tenant $store): ?bool => $store->delete());
}
