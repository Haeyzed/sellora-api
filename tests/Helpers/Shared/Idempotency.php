<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| For EnsureRequestIsIdempotentTest.php
|--------------------------------------------------------------------------
*/

function chargeWithKey(string $key, array $body = ['amount' => 1000], string $ip = '203.0.113.10'): Illuminate\Testing\TestResponse
{
    return test()->withHeader('Idempotency-Key', $key)
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/sample-charges', $body);
}
