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
