<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use Stancl\Tenancy\Commands\Seed;

/**
 * Runs the store seeders on every store with a database (tenants:seed). Skips stores that have no database yet; see RunsOnStoresWithADatabase.
 */
final class SeedStoreDatabasesCommand extends Seed
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
