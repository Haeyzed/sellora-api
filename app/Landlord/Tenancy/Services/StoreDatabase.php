<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Jobs\SeedDatabase;

/**
 * Creates a store's database on its server, runs the store migrations and seeds the built-in data, such as the Owner role; and drops it when the store is purged.
 *
 * Every step is safe to run again: an existing database is kept, and
 * migrations and seeders only add what is missing. So a setup that failed
 * halfway can simply be retried. Dropping a database that is already gone
 * does nothing.
 */
final readonly class StoreDatabase
{
    public function __construct(private DatabaseManager $databases) {}

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

    /**
     * Deletes the store's database for good.
     *
     * This process's own connection to it is closed first. If another process
     * is still connected (an export being built), the server refuses and the
     * caller can try again later.
     */
    public function drop(Tenant $tenant): void
    {
        $databaseConfig = $tenant->database();
        $databaseManager = $databaseConfig->manager();

        if (! $databaseManager->databaseExists((string) $databaseConfig->getName())) {
            return;
        }

        $this->databases->purge('tenant');
        $databaseManager->deleteDatabase($tenant);
    }
}
