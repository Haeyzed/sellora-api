<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Idempotency\IdempotencyStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| For ProcessDatabaseTest.php
|--------------------------------------------------------------------------
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

/*
|--------------------------------------------------------------------------
| For QueueWorkersTest.php
|--------------------------------------------------------------------------
*/

/**
 * The queues named in a command's "--queue=" option in a file of the project.
 *
 * @return list<string>
 */
function queuesListenedIn(string $path, string $command): array
{
    preg_match('/'.preg_quote($command, '/').' --queue=([a-z0-9_,-]+)/', (string) file_get_contents(base_path($path)), $matches);

    return isset($matches[1]) ? explode(',', $matches[1]) : [];
}

/*
|--------------------------------------------------------------------------
| For StoreIsolationTest.php
|--------------------------------------------------------------------------
*/

function seedExpiredIdempotencyKey(Tenant $store, string $key): void
{
    $store->run(static function () use ($key): void {
        IdempotencyKey::query()->create([
            'scope' => 'ip:test',
            'route' => 'POST sample',
            'key' => $key,
            'request_hash' => str_repeat('a', 64),
            'status' => IdempotencyStatus::Completed,
            'locked_until' => now()->subWeek(),
            'expires_at' => now()->subWeek(),
        ]);
    });
}
