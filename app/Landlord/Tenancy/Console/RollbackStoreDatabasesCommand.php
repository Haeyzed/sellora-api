<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use Stancl\Tenancy\Commands\Rollback;

/**
 * Rolls back the last store migrations on every store with a database (tenants:rollback). Skips stores that have no database yet; see RunsOnStoresWithADatabase.
 */
final class RollbackStoreDatabasesCommand extends Rollback
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
