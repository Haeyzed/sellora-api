<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\StoreDatabase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Gives test stores a database that is an exact copy of one prepared store database, instead of migrating a new one for every test (section 15).
 *
 * The template is built once per test process, the first time a test needs a
 * store, through the same StoreDatabase::prepare() real store setup uses, so
 * it is exactly what setup produces from the migrations and seeders on disk
 * now. A test store gets either a new copy of it (CREATE DATABASE ...
 * TEMPLATE) or a copy an earlier test in this process finished with, reset to
 * the template's exact state: every table emptied, the template's built-in
 * rows (the Owner role, the permissions) put back, and every ID counter set
 * back. A reset copy is reused only if its fingerprint (schema, every table's
 * rows, counters, extensions) matches the template's; otherwise it is dropped
 * and a fresh copy made. So every test still starts from a database identical
 * to a new copy, but most tests skip creating and dropping a database, which
 * on PostgreSQL forces a checkpoint and costs seconds per store under
 * parallel load. A test proves a copy matches a store set up for real
 * (TenantProvisioningTest).
 *
 * Store databases are named with a per-process prefix (TestCase), so building
 * the template first drops whatever an interrupted earlier run in the same
 * process slot left behind, without touching other processes' databases.
 */
final class StoreDatabaseTemplate
{
    private const string POOL_CONNECTION = 'test_store_pool';

    private static ?string $template = null;

    /** The template's fingerprint, compared after every reset. */
    private static string $fingerprint = '';

    /** @var array<string, string> Each table holding built-in rows in the template, with those rows as JSON. */
    private static array $builtInRows = [];

    /** @var array{names: list<string>, values: list<int>, called: list<bool>} Every ID counter's value in the template. */
    private static array $counters = ['names' => [], 'values' => [], 'called' => []];

    /** @var list<string> Every table in the template, schema-qualified and quoted. */
    private static array $tables = [];

    /** @var list<string> Reset copies waiting for the next test store. */
    private static array $pool = [];

    private static int $pooled = 0;

    /**
     * Gives the store its database: a reset copy from the pool if there is one (and reuse is allowed), otherwise a new copy of the template.
     */
    public static function copyInto(Tenant $store, bool $mayReuse = true): void
    {
        if ($store->database_server_id !== null) {
            throw new LogicException('Test stores copied from the template must live on the central database server.');
        }

        $template = self::$template ??= self::build();
        $databaseConfig = $store->database();
        $databaseConfig->makeCredentials();
        $name = (string) $databaseConfig->getName();
        $pooled = $mayReuse ? array_pop(self::$pool) : null;

        self::server()->statement($pooled !== null
            ? sprintf('alter database %s rename to %s', self::quoted($pooled), self::quoted($name))
            : sprintf('create database %s template %s', self::quoted($name), self::quoted($template)));
    }

    /**
     * Takes back a deleted test store's database: reset into the pool when it can be made identical to the template, otherwise dropped.
     *
     * Returns false when the store has no database on the central server, so the caller deletes it as usual.
     */
    public static function release(Tenant $store): bool
    {
        $name = (string) $store->database()->getName();

        if (self::$template === null || $store->database_server_id !== null || ! self::exists($name)) {
            return false;
        }

        $server = self::server();
        $server->select('select pg_terminate_backend(pid) from pg_stat_activity where datname = ? and pid <> pg_backend_pid()', [$name]);

        try {
            $isLikeTheTemplate = self::reset($name);
        } catch (Throwable) {
            $isLikeTheTemplate = false;
        } finally {
            DB::purge(self::POOL_CONNECTION);
        }

        if (! $isLikeTheTemplate) {
            $server->statement('drop database '.self::quoted($name).' with (force)');

            return true;
        }

        $pooled = self::$template.'_pool_'.++self::$pooled;
        $server->statement(sprintf('alter database %s rename to %s', self::quoted($name), self::quoted($pooled)));
        self::$pool[] = $pooled;

        return true;
    }

    /**
     * Prepares a store database for real, records what reset needs, then turns it into this process's template.
     */
    private static function build(): string
    {
        $server = self::server();
        $prefix = config()->string('tenancy.database.prefix');
        // Databases of stores the current test already made are not leftovers.
        $inUse = Tenant::query()->get()->map(static fn (Tenant $store): string => (string) $store->database()->getName())->all();

        foreach ($server->select('select datname from pg_database where starts_with(datname, ?)', [$prefix]) as $database) {
            if (! in_array($database->datname, $inUse, true)) {
                $server->statement('drop database if exists '.self::quoted($database->datname).' with (force)');
            }
        }

        $blueprint = Tenant::factory()->create();
        app(StoreDatabase::class)->prepare($blueprint);
        $prepared = (string) $blueprint->database()->getName();

        tenancy()->end();
        DB::purge('tenant');
        Tenant::withoutEvents(static fn (): ?bool => $blueprint->delete());

        try {
            self::record(self::poolConnection($prepared));
        } finally {
            DB::purge(self::POOL_CONNECTION);
        }

        $template = $prefix.'template';
        $server->statement(sprintf('alter database %s rename to %s', self::quoted($prepared), self::quoted($template)));
        // Nobody can connect to it, so a stray connection can never block a copy.
        $server->statement(sprintf('alter database %s with allow_connections false', self::quoted($template)));

        return $template;
    }

    /**
     * Notes the template's tables, built-in rows, counters and fingerprint.
     */
    private static function record(Connection $template): void
    {
        self::$tables = array_map(
            static fn (object $table): string => self::quoted($table->schemaname).'.'.self::quoted($table->tablename),
            $template->select("select schemaname, tablename from pg_tables where schemaname not in ('pg_catalog', 'information_schema') order by 1, 2"),
        );

        self::$builtInRows = [];
        foreach (self::tablesWithRows($template) as $table) {
            self::$builtInRows[$table] = (string) $template->selectOne("select json_agg(t) as rows from {$table} t")->rows;
        }

        self::$counters = ['names' => [], 'values' => [], 'called' => []];
        foreach ($template->select('select format(\'%I.%I\', schemaname, sequencename) as name, coalesce(last_value, start_value) as value, last_value is not null as called from pg_sequences order by 1') as $counter) {
            self::$counters['names'][] = $counter->name;
            self::$counters['values'][] = (int) $counter->value;
            self::$counters['called'][] = (bool) $counter->called;
        }

        self::$fingerprint = self::fingerprintOf($template);
    }

    /**
     * Empties a used copy, puts the template's built-in rows and counters back, and says whether it now matches the template exactly.
     */
    private static function reset(string $name): bool
    {
        $copy = self::poolConnection($name);

        $copy->transaction(static function () use ($copy): void {
            $toEmpty = array_values(array_unique([...self::tablesWithRows($copy), ...array_keys(self::$builtInRows)]));

            if ($toEmpty !== []) {
                $copy->statement('truncate table '.implode(', ', $toEmpty).' restart identity cascade');
            }

            // Foreign keys are checked by triggers, which replica mode skips while the rows go back in whatever order.
            $copy->statement('set local session_replication_role = replica');
            foreach (self::$builtInRows as $table => $rows) {
                $copy->insert("insert into {$table} select * from json_populate_recordset(null::{$table}, ?::json)", [$rows]);
            }
            $copy->statement('set local session_replication_role = origin');

            if (self::$counters['names'] !== []) {
                $copy->select(
                    'select setval(name::regclass, value, called) from unnest(?::text[], ?::bigint[], ?::boolean[]) as counter(name, value, called)',
                    [self::pgArray(self::$counters['names']), self::pgArray(array_map(strval(...), self::$counters['values'])), self::pgArray(array_map(static fn (bool $called): string => $called ? 't' : 'f', self::$counters['called']))],
                );
            }
        });

        return self::fingerprintOf($copy) === self::$fingerprint;
    }

    /**
     * One hash of everything a test could see in a store database: its schema, extensions, every table's rows and every counter.
     */
    private static function fingerprintOf(Connection $database): string
    {
        $schema = (string) $database->selectOne(<<<'SQL'
            select md5(string_agg(item, E'\n' order by item)) as hash from (
                select 'column ' || table_schema || '.' || table_name || '.' || column_name || ' ' || data_type || ' ' || is_nullable || ' ' || coalesce(column_default, '') as item
                    from information_schema.columns where table_schema not in ('pg_catalog', 'information_schema')
                union all select 'constraint ' || conrelid::regclass::text || '.' || conname || ' ' || pg_get_constraintdef(oid)
                    from pg_constraint where connamespace not in ('pg_catalog'::regnamespace, 'information_schema'::regnamespace)
                union all select 'index ' || schemaname || '.' || indexname || ' ' || indexdef from pg_indexes where schemaname not in ('pg_catalog', 'information_schema')
                union all select 'trigger ' || tgrelid::regclass::text || '.' || tgname from pg_trigger where not tgisinternal
                union all select 'function ' || oid::regprocedure::text from pg_proc where pronamespace not in ('pg_catalog'::regnamespace, 'information_schema'::regnamespace)
                union all select 'schema ' || nspname from pg_namespace
                union all select 'extension ' || extname || ' ' || extversion from pg_extension
                union all select 'counter ' || schemaname || '.' || sequencename || ' ' || coalesce(last_value::text, 'unused') from pg_sequences
            ) as items
            SQL)->hash;

        $rows = self::$tables === [] ? '' : (string) $database->selectOne('select md5(concat('.implode(', ', array_map(
            static fn (string $table): string => "(select coalesce(string_agg(t::text, ',' order by t::text), '') from {$table} t), '|'",
            self::$tables,
        )).')) as hash')->hash;

        return $schema.$rows;
    }

    /**
     * @return list<string>
     */
    private static function tablesWithRows(Connection $database): array
    {
        if (self::$tables === []) {
            return [];
        }

        return array_column($database->select(implode(' union all ', array_map(
            static fn (string $table): string => "select '".str_replace("'", "''", $table)."' as name where exists (select 1 from {$table})",
            self::$tables,
        ))), 'name');
    }

    private static function exists(string $name): bool
    {
        return self::server()->selectOne('select exists (select 1 from pg_database where datname = ?) as found', [$name])->found;
    }

    private static function poolConnection(string $database): Connection
    {
        config(['database.connections.'.self::POOL_CONNECTION => [...config()->array('database.connections.'.config()->string('tenancy.database.central_connection')), 'database' => $database]]);
        DB::purge(self::POOL_CONNECTION);

        return DB::connection(self::POOL_CONNECTION);
    }

    private static function server(): Connection
    {
        return DB::connection(config()->string('tenancy.database.central_connection'));
    }

    /**
     * @param  list<string>  $values
     */
    private static function pgArray(array $values): string
    {
        return '{'.implode(',', array_map(static fn (string $value): string => '"'.addcslashes($value, '"\\').'"', $values)).'}';
    }

    private static function quoted(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
