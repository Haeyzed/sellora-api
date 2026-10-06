<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use Stancl\Tenancy\Commands\Migrate;

/**
 * Runs the store migrations on every store with a database (tenants:migrate). Skips stores that have no database yet; see RunsOnStoresWithADatabase.
 */
final class MigrateStoreDatabasesCommand extends Migrate
{
    use RunsOnStoresWithADatabase;

    public function handle(): mixed
    {
        if (! $this->targetStoresWithADatabase()) {
            return self::SUCCESS;
        }

        return parent::handle();
    }
}
