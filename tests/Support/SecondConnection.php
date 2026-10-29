<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * A second connection to the same database, as a concurrent request would have, for testing locks under real contention (section 13).
 *
 * It works inside its own open transaction, so the locks it takes and the
 * rows it writes stay held and unseen until commit() or rollBack(). While it
 * holds a lock, blocks() proves that work on the main connection waits for
 * that lock instead of going ahead.
 *
 *     $other = SecondConnection::open()->holdAdvisoryLock('products');
 *     expect($other->blocks(fn () => $createProduct()))->toBeTrue();
 *     $other->commit();
 */
final class SecondConnection
{
    /** The connection's name, for models and factories that should write through it. */
    public const string NAME = 'second_connection';

    /** SQLSTATE lock_not_available: a statement gave up waiting for a lock. */
    private const string LOCK_NOT_AVAILABLE = '55P03';

    private function __construct(
        private readonly Connection $connection,
        private readonly string $mainConnectionName,
    ) {}

    /**
     * Opens a second connection to the database the given connection uses (the default one when left out), and starts its transaction.
     */
    public static function open(?string $connectionName = null): self
    {
        $main = DB::connection($connectionName);
        $config = $main->getConfig();
        unset($config['name']);

        DB::purge(self::NAME);
        config()->set('database.connections.'.self::NAME, $config);

        $connection = DB::connection(self::NAME);
        $connection->beginTransaction();

        return new self($connection, (string) $main->getName());
    }

    /**
     * Takes a transaction-level advisory lock, as the app's pg_advisory_xact_lock(hashtext(key)) calls do.
     */
    public function holdAdvisoryLock(string $key): self
    {
        $this->connection->select('select pg_advisory_xact_lock(hashtext(?))', [$key]);

        return $this;
    }

    /**
     * Locks a model's row, for update unless another row lock mode is given (such as "for no key update"), as a request changing it would.
     */
    public function holdRowLock(Model $model, string $mode = 'for update'): self
    {
        $this->connection->table($model->getTable())->where($model->getKeyName(), $model->getKey())->lock($mode)->first();

        return $this;
    }

    /**
     * Runs work inside this connection's transaction, such as writing what a concurrent request would.
     *
     * @template TResult
     *
     * @param  Closure(Connection): TResult  $work
     * @return TResult
     */
    public function run(Closure $work): mixed
    {
        return $work($this->connection);
    }

    /**
     * Runs the application's own code on this connection, inside its transaction, as a concurrent request would: it is the default connection until the work returns, so what the work locks and writes stays locked and unseen until commit() or rollBack().
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public function act(Closure $work): mixed
    {
        $main = DB::getDefaultConnection();
        DB::setDefaultConnection(self::NAME);

        try {
            return $work();
        } finally {
            DB::setDefaultConnection($main);
        }
    }

    /**
     * Whether work on the main connection has to wait for a lock this connection holds. Waits are cut short after the timeout and the work's transaction is rolled back.
     *
     * Any other failure is rethrown, so a test can't mistake it for waiting.
     */
    public function blocks(Closure $work, int $timeoutMilliseconds = 500): bool
    {
        return $this->blockedStatement($work, $timeoutMilliseconds) !== null;
    }

    /**
     * The statement of work on the main connection that had to wait for a lock this connection holds, or null when nothing waited.
     *
     * Shows where the wait happens, such as the work's own row lock rather
     * than a later write that would wait anyway.
     */
    public function blockedStatement(Closure $work, int $timeoutMilliseconds = 500): ?string
    {
        $main = DB::connection($this->mainConnectionName);
        $main->statement("set lock_timeout = '{$timeoutMilliseconds}ms'");

        try {
            $work();

            return null;
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::LOCK_NOT_AVAILABLE) {
                throw $exception;
            }

            return $exception->getSql();
        } finally {
            $main->statement('reset lock_timeout');
        }
    }

    /**
     * Commits what this connection wrote and releases its locks.
     */
    public function commit(): void
    {
        $this->connection->commit();
        $this->close();
    }

    /**
     * Throws away what this connection wrote and releases its locks.
     */
    public function rollBack(): void
    {
        $this->connection->rollBack();
        $this->close();
    }

    private function close(): void
    {
        DB::purge(self::NAME);
        config()->set('database.connections.'.self::NAME, null);
    }
}
