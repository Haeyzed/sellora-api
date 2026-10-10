<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ProcessDatabase;

/*
 * Section 15: each parallel test process gets its own central database,
 * created once when missing and never dropped or recreated, so a failed
 * check can't destroy a database that is in use.
 */

/**
 * Runs the work with a scratch database name, dropping whatever was created afterwards.
 */
function withScratchProcessDatabase(Closure $work): void
{
    $connection = config()->string('database.default');
    $base = config()->string("database.connections.{$connection}.database");
    $token = 'probe'.Str::lower(Str::random(8));
    $database = "{$base}_test_{$token}";

    try {
        $work($connection, $base, $token, $database);
    } finally {
        config(["database.connections.{$connection}.database" => $base]);
        DB::purge($connection);
        DB::purge('scratch');
        DB::connection($connection)->statement('drop database if exists "'.$database.'" with (force)');
    }
}

it('creates the process database when it is missing, and points the connection at it', function (): void {
    withScratchProcessDatabase(static function (string $connection, string $base, string $token, string $database): void {
        expect(ProcessDatabase::switchTo($connection, $token))->toBe($database)
            ->and(config("database.connections.{$connection}.database"))->toBe($database);

        config(["database.connections.{$connection}.database" => $base]);
        DB::purge($connection);
        expect(DB::connection($connection)->selectOne('select exists (select 1 from pg_database where datname = ?) as found', [$database])->found)->toBeTrue();
    });
});

it('never drops or recreates a process database that exists, even while it is in use', function (): void {
    withScratchProcessDatabase(static function (string $connection, string $base, string $token, string $database): void {
        DB::connection($connection)->statement('create database "'.$database.'"');
        config(['database.connections.scratch' => [...config()->array("database.connections.{$connection}"), 'database' => $database]]);
        DB::connection('scratch')->statement('create table kept (id int)');
        $identity = DB::connection($connection)->selectOne('select oid from pg_database where datname = ?', [$database])->oid;

        // The connection stays open, as another part of the process would keep it.
        ProcessDatabase::switchTo($connection, $token);

        config(["database.connections.{$connection}.database" => $base]);
        DB::purge($connection);
        expect(DB::connection($connection)->selectOne('select oid from pg_database where datname = ?', [$database])->oid)->toBe($identity)
            ->and(DB::connection('scratch')->select("select 1 from pg_tables where tablename = 'kept'"))->toHaveCount(1);
    });
});

it('switches off Laravel\'s own test database handling, which drops and recreates a database when a check fails', function (): void {
    expect(Illuminate\Support\Facades\ParallelTesting::option('without_databases'))->toBeTrue();
});
