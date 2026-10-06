<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['api.rate_limits.login' => 3]);

    Route::post('/api/v1/platform/sample-login', static fn (): string => 'ok')->middleware('throttle:login');
});

function attemptLogin(string $email, string $ip = '203.0.113.10'): Illuminate\Testing\TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/platform/sample-login', ['email' => $email]);
}

it('blocks sign-in attempts for an account after the limit, with a Retry-After header', function (): void {
    attemptLogin('ada@example.com')->assertOk();
    attemptLogin('ada@example.com')->assertOk();
    attemptLogin('ada@example.com')->assertOk();

    attemptLogin('ada@example.com')->assertTooManyRequests()->assertHeader('Retry-After');
});

it('counts differently formatted versions of the same email as one account', function (): void {
    attemptLogin('ada@example.com');
    attemptLogin('ADA@Example.com');
    attemptLogin('  ada@example.COM ');

    attemptLogin('Ada@example.com')->assertTooManyRequests();
});

it('keeps counting separately for a different account', function (): void {
    attemptLogin('ada@example.com');
    attemptLogin('ada@example.com');
    attemptLogin('ada@example.com');

    attemptLogin('grace@example.com')->assertOk();
});
