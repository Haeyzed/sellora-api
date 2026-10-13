<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\StoreDatabase;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use LogicException;
use Throwable;

/**
 * Gives test stores a database that is an exact copy of one prepared store database, instead of migrating a new one for every test (section 15).
 *
 * In a parallel run the template is built once, before the workers start
 * (prepareForParallelRun(), called from Tests\CreatesApplication), through
 * the same StoreDatabase::prepare() real store setup uses, so it is exactly
 * what setup produces from the migrations and seeders on disk now; every
 * worker copies it. A run of one process builds it when its first store is
 * made. A test store gets either a new copy (CREATE DATABASE ... TEMPLATE)
 * or a copy an earlier test in this process finished with, reset to the
 * template's exact state: every table emptied, the template's built-in rows
 * (the Owner role, the permissions) put back, and every ID counter set back.
 * A reset copy is reused only if it then matches the template: the same
 * schema (a catalog check), the same tables holding rows with the same rows,
 * and the same counters. Otherwise it is dropped and a fresh copy made. Most
 * tests so skip creating and dropping a database, which on PostgreSQL forces
 * a checkpoint and costs seconds per store under parallel load. A test
 * proves a copy matches a store set up for real (TenantProvisioningTest).
 *
 * Each process's store databases are named with its own prefix (TestCase),
 * so processes never touch each other's databases.
 */
final class StoreDatabaseTemplate
{
    private const string POOL_CONNECTION = 'test_store_pool';

    /** PostgreSQL's error for a database someone is connected to. */
    private const string IN_USE = '55006';

    /** The central database the template is built from before a parallel run. */
    private const string SETUP_TOKEN = 'setup';

    /** The store database prefix before TestCase adds the process's own part, such as "sellora_testing_tenant_". */
    private static string $basePrefix = '';

    private static bool $preparedForParallelRun = false;

    private static ?string $template = null;

    /** Whether the template's state below has been recorded in this process. */
    private static bool $recorded = false;

    /** The template's schema fingerprint, compared after every reset. */
    private static string $schemaFingerprint = '';

    /** @var array<string, string> Each table holding built-in rows in the template, with those rows as JSON. */
    private static array $builtInRows = [];

    /** The md5 of the built-in rows as the template holds them, compared after every reset. */
    private static string $builtInRowsFingerprint = '';

    /** @var array{names: list<string>, values: list<int>, called: list<bool>} Every ID counter's value in the template. */
    private static array $counters = ['names' => [], 'values' => [], 'called' => []];

    /** @var list<string> Every table in the template, schema-qualified and quoted. */
    private static array $tables = [];

    /** @var list<string> Reset copies waiting for the next test store. */
    private static array $pool = [];

    private static int $pooled = 0;

    /**
     * Notes the store database prefix before the process's own part is added (TestCase::setUp()).
     */
    public static function useBasePrefix(string $basePrefix): void
    {
        self::$basePrefix = $basePrefix;
    }

    /**
     * Builds the template for a parallel run, once, before any worker starts: drops every test store database an earlier run left, then prepares a store database for real and turns it into the template.
     *
     * Runs in the main process with its own central database, so no worker's
     * database is touched while it could be in use.
     */
    public static function prepareForParallelRun(): void
    {
        if (self::$preparedForParallelRun) {
            return;
        }

        self::$preparedForParallelRun = true;
        $basePrefix = config()->string('tenancy.database.prefix');
        $central = config()->string('tenancy.database.central_connection');

        ProcessDatabase::switchTo($central, self::SETUP_TOKEN);
        Artisan::call('migrate:fresh', ['--force' => true]);

        // No worker has started, so every test store database is left over from an earlier run.
        foreach (self::server()->select('select datname from pg_database where starts_with(datname, ?)', [$basePrefix]) as $database) {
            self::server()->statement('drop database if exists '.self::quoted($database->datname).' with (force)');
        }

        config(['tenancy.database.prefix' => $basePrefix.self::SETUP_TOKEN.'_']);
        self::turnIntoTemplate(self::prepareBlueprint(), $basePrefix.'template');
    }

    /**
     * Gives the store its database: a reset copy from the pool if there is one (and reuse is allowed), otherwise a new copy of the template.
     */
    public static function copyInto(Tenant $store, bool $mayReuse = true): void
    {
        if ($store->database_server_id !== null) {
            throw new LogicException('Test stores copied from the template must live on the central database server.');
        }

        $template = self::$template ??= self::templateForThisProcess();
        $databaseConfig = $store->database();
        $databaseConfig->makeCredentials();
        $name = (string) $databaseConfig->getName();
        $pooled = $mayReuse ? array_pop(self::$pool) : null;

        if ($pooled !== null) {
            self::whileUnused($pooled, sprintf('alter database %s rename to %s', self::quoted($pooled), self::quoted($name)));

            return;
        }

        self::server()->statement(sprintf('create database %s template %s', self::quoted($name), self::quoted($template)));

        // Built before the run by another process: a fresh copy is identical to it, so what reset needs is recorded from the copy.
        if (! self::$recorded) {
            try {
                self::record(self::poolConnection($name));
            } finally {
                DB::purge(self::POOL_CONNECTION);
            }
        }
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

        self::endOtherSessions($name);

        try {
            $isLikeTheTemplate = self::reset($name);
        } catch (Throwable) {
            $isLikeTheTemplate = false;
        } finally {
            DB::purge(self::POOL_CONNECTION);
        }

        if (! $isLikeTheTemplate) {
            self::server()->statement('drop database '.self::quoted($name).' with (force)');

            return true;
        }

        $pooled = config()->string('tenancy.database.prefix').'pool_'.++self::$pooled;
        self::whileUnused($name, sprintf('alter database %s rename to %s', self::quoted($name), self::quoted($pooled)));
        self::$pool[] = $pooled;

        return true;
    }

    /**
     * The template this process copies: in a parallel worker the one built before the run, which must exist; in a run of one process, one it builds now.
     */
    private static function templateForThisProcess(): string
    {
        $token = ParallelTesting::token();

        if (empty($_SERVER['LARAVEL_PARALLEL_TESTING']) || ! is_string($token) || $token === '') {
            return self::buildForThisProcess();
        }

        $template = self::$basePrefix.'template';

        if (self::$basePrefix === '' || ! self::exists($template)) {
            throw new LogicException("The store template [{$template}] is built before the parallel workers start (Tests\\CreatesApplication), but it does not exist.");
        }

        return $template;
    }

    /**
     * A run of one process: drops whatever an interrupted earlier run in this process slot left behind, then builds the template and records it.
     */
    private static function buildForThisProcess(): string
    {
        $prefix = config()->string('tenancy.database.prefix');
        // Databases of stores the current test already made are not leftovers.
        $inUse = Tenant::query()->get()->map(static fn (Tenant $store): string => (string) $store->database()->getName())->all();

        foreach (self::server()->select('select datname from pg_database where starts_with(datname, ?)', [$prefix]) as $database) {
            if (! in_array($database->datname, $inUse, true)) {
                self::server()->statement('drop database if exists '.self::quoted($database->datname).' with (force)');
            }
        }

        $prepared = self::prepareBlueprint();

        try {
            self::record(self::poolConnection($prepared));
        } finally {
            DB::purge(self::POOL_CONNECTION);
        }

        $template = $prefix.'template';
        self::turnIntoTemplate($prepared, $template);

        return $template;
    }

    /**
     * Prepares a store database for real, as store setup does, and returns its name; the store row used for it is removed.
     */
    private static function prepareBlueprint(): string
    {
        $blueprint = Tenant::factory()->create();
        app(StoreDatabase::class)->prepare($blueprint);
        $prepared = (string) $blueprint->database()->getName();

        tenancy()->end();
        DB::purge('tenant');
        Tenant::withoutEvents(static fn (): ?bool => $blueprint->delete());

        return $prepared;
    }

    private static function turnIntoTemplate(string $prepared, string $template): void
    {
        self::whileUnused($prepared, sprintf('alter database %s rename to %s', self::quoted($prepared), self::quoted($template)));
        // Nobody can connect to it, so a stray connection can never block a copy.
        self::server()->statement(sprintf('alter database %s with allow_connections false', self::quoted($template)));
    }

    /**
     * Notes the template's tables, built-in rows, counters and schema fingerprint, from the template or a fresh copy of it.
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
        foreach (self::countersOf($template) as $counter) {
            self::$counters['names'][] = $counter->name;
            self::$counters['values'][] = (int) $counter->value;
            self::$counters['called'][] = (bool) $counter->called;
        }

        self::$schemaFingerprint = self::schemaFingerprintOf($template);
        self::$builtInRowsFingerprint = self::builtInRowsFingerprintOf($template);
        self::$recorded = true;
    }

    /**
     * Empties a used copy, puts the template's built-in rows and counters back, and says whether it now matches the template exactly.
     */
    private static function reset(string $name): bool
    {
        $copy = self::poolConnection($name);

        $copy->transaction(static function () use ($copy): void {
            $toEmpty = array_values(array_unique([...self::tablesWithRows($copy), ...array_keys(self::$builtInRows)]));

            // Foreign keys are checked by triggers, which replica mode skips, so tables are emptied and the rows go back in whatever order.
            $copy->statement('set local session_replication_role = replica');

            // DELETE, not TRUNCATE: a test leaves a few rows, and TRUNCATE gives every table new files on disk, which is
            // slow under parallel load (on Windows especially). The counters are set back below either way.
            foreach ($toEmpty as $table) {
                $copy->delete("delete from {$table}");
            }

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

        return self::matchesTheTemplate($copy);
    }

    /**
     * Whether a reset copy is exactly what a fresh copy would be: the same schema, only the built-in tables holding rows and with the same rows, and the same counters.
     */
    private static function matchesTheTemplate(Connection $copy): bool
    {
        $builtInTables = array_keys(self::$builtInRows);
        $tablesWithRows = self::tablesWithRows($copy);
        sort($builtInTables);
        sort($tablesWithRows);

        if ($tablesWithRows !== $builtInTables || self::builtInRowsFingerprintOf($copy) !== self::$builtInRowsFingerprint) {
            return false;
        }

        $counters = ['names' => [], 'values' => [], 'called' => []];
        foreach (self::countersOf($copy) as $counter) {
            $counters['names'][] = $counter->name;
            $counters['values'][] = (int) $counter->value;
            $counters['called'][] = (bool) $counter->called;
        }

        return $counters === self::$counters && self::schemaFingerprintOf($copy) === self::$schemaFingerprint;
    }

    /**
     * One hash of the schema a test could see or change, read from the system catalogs: every column (type, nullability, default), constraint, index, trigger, function, schema, extension and counter.
     *
     * The catalogs answer this in milliseconds; the information_schema views
     * checked privileges on every row and took a large share of each reset.
     */
    private static function schemaFingerprintOf(Connection $database): string
    {
        return (string) $database->selectOne(<<<'SQL'
            with user_namespaces as (
                select oid from pg_namespace where nspname not in ('pg_catalog', 'information_schema') and nspname not like 'pg_toast%' and nspname not like 'pg_temp%'
            )
            select md5(string_agg(item, E'\n' order by item)) as hash from (
                select 'column ' || c.oid::regclass::text || '.' || a.attname || ' ' || format_type(a.atttypid, a.atttypmod) || ' ' || a.attnotnull::text || ' ' || coalesce(pg_get_expr(d.adbin, d.adrelid), '') as item
                    from pg_attribute a
                    join pg_class c on c.oid = a.attrelid and c.relkind in ('r', 'p', 'v', 'm', 'f')
                    left join pg_attrdef d on d.adrelid = a.attrelid and d.adnum = a.attnum
                    where c.relnamespace in (select oid from user_namespaces) and a.attnum > 0 and not a.attisdropped
                union all select 'constraint ' || conrelid::regclass::text || '.' || conname || ' ' || pg_get_constraintdef(oid)
                    from pg_constraint where connamespace in (select oid from user_namespaces)
                union all select 'index ' || i.indexrelid::regclass::text || ' ' || pg_get_indexdef(i.indexrelid)
                    from pg_index i join pg_class c on c.oid = i.indexrelid where c.relnamespace in (select oid from user_namespaces)
                union all select 'trigger ' || tgrelid::regclass::text || '.' || tgname from pg_trigger where not tgisinternal
                union all select 'function ' || oid::regprocedure::text from pg_proc where pronamespace in (select oid from user_namespaces)
                union all select 'schema ' || nspname from pg_namespace
                union all select 'extension ' || extname || ' ' || extversion from pg_extension
                union all select 'counter ' || c.oid::regclass::text from pg_class c where c.relkind = 'S' and c.relnamespace in (select oid from user_namespaces)
            ) as items
            SQL)->hash;
    }

    /**
     * One hash of the rows in the tables that hold built-in rows in the template.
     */
    private static function builtInRowsFingerprintOf(Connection $database): string
    {
        if (self::$builtInRows === []) {
            return '';
        }

        return (string) $database->selectOne('select md5(concat('.implode(', ', array_map(
            static fn (string $table): string => "(select coalesce(string_agg(t::text, ',' order by t::text), '') from {$table} t), '|'",
            array_keys(self::$builtInRows),
        )).')) as hash')->hash;
    }

    /**
     * @return list<object{name: string, value: int|string, called: bool}>
     */
    private static function countersOf(Connection $database): array
    {
        /** @var list<object{name: string, value: int|string, called: bool}> */
        return $database->select('select format(\'%I.%I\', schemaname, sequencename) as name, coalesce(last_value, start_value) as value, last_value is not null as called from pg_sequences order by 1');
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

    /**
     * Runs a statement that needs nobody connected to the database, such as renaming it.
     *
     * Only this test process uses its store databases, so any other session on
     * one is a stale connection of this process (or a server worker passing
     * through), which is ended. Windows can also leave a closed connection
     * registered for a moment, so the statement is tried a few times.
     */
    private static function whileUnused(string $database, string $statement): void
    {
        for ($attempt = 1; ; $attempt++) {
            self::endOtherSessions($database);

            try {
                self::server()->statement($statement);

                return;
            } catch (QueryException $exception) {
                if ($exception->getCode() !== self::IN_USE || $attempt === 10) {
                    throw $exception;
                }

                usleep(200_000);
            }
        }
    }

    private static function endOtherSessions(string $database): void
    {
        self::server()->select('select pg_terminate_backend(pid) from pg_stat_activity where datname = ? and pid <> pg_backend_pid()', [$database]);
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
