<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Str;

/**
 * The common parent of every test that boots the application.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Passwords the fake breached-password service reports as breached.
     *
     * @var list<string>
     */
    private array $breachedPasswords = [];

    private bool $breachedPasswordServiceIsDown = false;

    /**
     * Tests never call real outside services. The fake breached-password service answers like the real one, from markAsBreached().
     *
     * Store databases get a prefix of their own per test process, so parallel
     * processes never share or clean up each other's store databases (Laravel
     * already gives each process its own central database).
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.database.prefix' => config()->string('tenancy.database.prefix').(ParallelTesting::token() ?: '0').'_']);

        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/range/*' => fn (Request $request): PromiseInterface => Http::response($this->breachedPasswordRange($request))]);
    }

    /**
     * Makes the breached-password check reject these passwords.
     */
    protected function markAsBreached(string ...$passwords): void
    {
        $this->breachedPasswords = [...$this->breachedPasswords, ...array_values($passwords)];
    }

    /**
     * Makes the breached-password service fail to answer, as in an outage.
     */
    protected function takeBreachedPasswordServiceDown(): void
    {
        $this->breachedPasswordServiceIsDown = true;
    }

    /**
     * The service's answer for one hash prefix: the hash suffixes it knows, each with how often it was seen.
     *
     * @throws ConnectionException When the service has been taken down.
     */
    private function breachedPasswordRange(Request $request): string
    {
        if ($this->breachedPasswordServiceIsDown) {
            throw new ConnectionException('The breached-password service is unavailable.');
        }

        $requestedPrefix = Str::afterLast($request->url(), '/');
        $lines = [];

        foreach ($this->breachedPasswords as $password) {
            $hash = mb_strtoupper(sha1($password));

            if (str_starts_with($hash, $requestedPrefix)) {
                $lines[] = mb_substr($hash, 5).':42';
            }
        }

        return implode("\r\n", $lines);
    }

    /**
     * Tests with real store databases (DatabaseTruncation) also empty the central database when they finish.
     *
     * Laravel truncates only before each such test, so without this their rows
     * (stores, platform admins, tokens) leak into the next transaction-based
     * test, which then depends on the order tests happen to run in.
     */
    protected function tearDown(): void
    {
        if (in_array(DatabaseTruncation::class, class_uses_recursive(static::class), true) && method_exists($this, 'truncateTablesForAllConnections')) {
            $this->truncateTablesForAllConnections();
        }

        parent::tearDown();
    }
}
