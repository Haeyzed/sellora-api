<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
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
