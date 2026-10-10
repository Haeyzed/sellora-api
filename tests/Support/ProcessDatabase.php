<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gives each parallel test process a central database of its own: created once if missing, never dropped or recreated.
 *
 * Laravel's own handling checks that database on a separate connection before
 * every test and, when the check fails for any reason (a dropped connection
 * is enough), drops and recreates it while the process may still be connected
 * to it. That failed twice in full runs on this machine. This makes the same
 * switch without ever dropping anything, and checks only once per process;
 * a failure here stops the test loudly instead of destroying a database.
 * Each database is still emptied and migrated by the database traits, as before.
 */
final class ProcessDatabase
{
    /** @var array<string, true> The databases this process has already made sure exist. */
    private static array $ready = [];

    /**
     * Points the connection at the process's own database, "<database>_test_<token>", creating it first if this process hasn't yet.
     */
    public static function switchTo(string $connection, string $token): string
    {
        $database = config()->string("database.connections.{$connection}.database").'_test_'.$token;

        if (! isset(self::$ready[$database])) {
            $server = DB::connection($connection);

            if (! $server->selectOne('select exists (select 1 from pg_database where datname = ?) as found', [$database])->found) {
                $server->statement('create database "'.str_replace('"', '""', $database).'"');
            }

            DB::purge($connection);
            self::$ready[$database] = true;
        }

        config(["database.connections.{$connection}.database" => $database]);

        return $database;
    }
}
