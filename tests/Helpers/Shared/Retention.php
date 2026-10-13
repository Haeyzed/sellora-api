<?php

declare(strict_types=1);

use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Idempotency\IdempotencyStatus;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| For PurgeExpiredRecordsTest.php
|--------------------------------------------------------------------------
*/

function createIdempotencyKey(string $key, Carbon\CarbonInterface $expiresAt): IdempotencyKey
{
    return IdempotencyKey::query()->create([
        'scope' => 'ip:test',
        'route' => 'POST sample',
        'key' => $key,
        'request_hash' => str_repeat('a', 64),
        'status' => IdempotencyStatus::Completed,
        'locked_until' => now(),
        'expires_at' => $expiresAt,
    ]);
}

function createAccessToken(string $name, Carbon\CarbonInterface $createdAt): PersonalAccessToken
{
    $token = PersonalAccessToken::query()->forceCreate([
        'tokenable_type' => 'sample',
        'tokenable_id' => 1,
        'name' => $name,
        'token' => hash('sha256', $name),
        'abilities' => ['*'],
    ]);
    $token->forceFill(['created_at' => $createdAt])->save();

    return $token;
}
