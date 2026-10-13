<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| For RateLimitServiceProviderTest.php
|--------------------------------------------------------------------------
*/

function attemptLogin(string $email, string $ip = '203.0.113.10'): Illuminate\Testing\TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/platform/sample-login', ['email' => $email]);
}
