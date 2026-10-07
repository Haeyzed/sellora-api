<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;

/**
 * Creates, checks and drops store databases on their server, closing the connection to the server after each statement.
 *
 * stancl/tenancy runs these statements through a connection to the store's
 * database server and never closes it. A worker would then hold one idle
 * connection per server for as long as it runs, still logged in with the
 * credentials it first used. These statements are rare, so reconnecting
 * each time costs nothing that matters. The central connection is left
 * open: the rest of the request is still using it.
 */
final class PostgreSQLStoreDatabaseManager extends PostgreSQLDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        return $this->closingServerConnection(fn (): bool => parent::createDatabase($tenant));
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        return $this->closingServerConnection(fn (): bool => parent::deleteDatabase($tenant));
    }

    public function databaseExists(string $name): bool
    {
        return $this->closingServerConnection(fn (): bool => parent::databaseExists($name));
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $statement
     * @return TResult
     */
    private function closingServerConnection(Closure $statement): mixed
    {
        try {
            return $statement();
        } finally {
            if ($this->connection !== config()->string('tenancy.database.central_connection')) {
                DB::purge($this->connection);
            }
        }
    }
}
