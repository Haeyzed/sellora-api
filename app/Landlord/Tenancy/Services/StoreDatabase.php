<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Tenancy\Models\Tenant;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Jobs\SeedDatabase;

/**
 * Creates a store's database on its server, runs the store migrations and seeds the built-in data, such as the Owner role.
 *
 * Every step is safe to run again: an existing database is kept, and
 * migrations and seeders only add what is missing. So a setup that failed
 * halfway can simply be retried.
 */
final readonly class StoreDatabase
{
    public function prepare(Tenant $tenant): void
    {
        $databaseConfig = $tenant->database();
        $databaseManager = $databaseConfig->manager();

        if (! $databaseManager->databaseExists((string) $databaseConfig->getName())) {
            $databaseConfig->makeCredentials();
            $databaseManager->createDatabase($tenant);
        }

        (new MigrateDatabase($tenant))->handle();
        (new SeedDatabase($tenant))->handle();
    }
}
