<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;

/**
 * For tests that create real store databases, which can't run inside a transaction: the central database is emptied by truncation, before and after each test (TestCase::tearDown).
 *
 * Laravel's DatabaseTruncation checks and truncates every table one by one,
 * two statements per table, twice per test. This asks once which tables hold
 * rows and empties only those, in one statement; on an already empty database
 * that is a single cheap query.
 */
trait TruncatesCentralDatabase
{
    use DatabaseTruncation;

    protected function truncateTablesForConnection(ConnectionInterface $connection, ?string $name): void
    {
        $exceptTables = $this->exceptTables($connection, $name);
        $tables = array_values(array_map(
            static fn (array $table): string => '"'.str_replace('"', '""', $table['schema']).'"."'.str_replace('"', '""', $table['name']).'"',
            array_filter($this->getAllTablesForConnection($connection, $name), fn (array $table): bool => ! $this->tableExistsIn($table, $exceptTables)),
        ));

        if ($tables === []) {
            return;
        }

        $withRows = array_column($connection->select(implode(' union all ', array_map(
            static fn (string $table): string => "select '".str_replace("'", "''", $table)."' as name where exists (select 1 from {$table})",
            $tables,
        ))), 'name');

        if ($withRows !== []) {
            $connection->statement('truncate table '.implode(', ', $withRows).' restart identity cascade');
        }
    }
}
