<?php

declare(strict_types=1);

use App\Shared\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

uses(LazilyRefreshDatabase::class);

final class SampleRuleBrokenException extends DomainException
{
    public function errorCode(): string
    {
        return 'sample_rule_broken';
    }

    public function status(): int
    {
        return 409;
    }
}

beforeEach(function (): void {
    Route::prefix('api/v1/test-errors')->group(function (): void {
        Route::post('/validation', static fn () => request()->validate(['email' => ['required', 'email']]));
        Route::get('/signed-in-only', static fn (): string => 'secret')->middleware('auth:staff');
        Route::get('/forbidden', static fn () => throw new AuthorizationException('Internal reason that must not leak'));
        Route::get('/business-rule', static fn () => throw new SampleRuleBrokenException(['rule' => 'minimum order']));
        Route::get('/crash', static fn () => throw new RuntimeException('SQLSTATE secret internal detail'));
        Route::get('/throttled', static fn (): string => 'ok')->middleware('throttle:1,1');
    });

    Route::middleware(['api', InitializeTenancyByDomain::class])->get('/api/v1/test-errors/store-only', static fn (): string => 'store');
});

it('returns 422 with field errors when validation fails', function (): void {
    $this->postJson('/api/v1/test-errors/validation', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => __('errors.validation_failed'),
            'code' => 'validation_failed',
            'errors' => ['email' => [__('validation.email', ['attribute' => 'email'])]],
        ]);
});

it('returns 401 as JSON instead of redirecting when a request without an Accept header is not signed in', function (): void {
    $this->get('/api/v1/test-errors/signed-in-only')
        ->assertUnauthorized()
        ->assertExactJson(['message' => __('errors.unauthenticated'), 'code' => 'unauthenticated', 'errors' => []]);
});

it('returns 403 without revealing the internal reason', function (): void {
    $this->getJson('/api/v1/test-errors/forbidden')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden')
        ->assertDontSee('Internal reason');
});

it('returns 404 for an endpoint that does not exist', function (): void {
    $this->getJson('/api/v1/test-errors/nowhere')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

it('returns 404 store_not_found for a store domain that does not exist, unlike a missing endpoint', function (): void {
    $this->getJson(storeUrl('no-such-store', '/api/v1/test-errors/store-only'))
        ->assertNotFound()
        ->assertJsonPath('code', 'store_not_found')
        ->assertJsonPath('message', __('errors.store_not_found'));
});

it('returns a business rule error with its own code, status and translated message', function (): void {
    Lang::addLines(['errors.sample_rule_broken' => 'The :rule rule was broken.'], 'en');

    $this->getJson('/api/v1/test-errors/business-rule')
        ->assertConflict()
        ->assertExactJson([
            'message' => 'The minimum order rule was broken.',
            'code' => 'sample_rule_broken',
            'errors' => [],
        ]);
});

it('hides internal details of unexpected errors when debugging is off', function (): void {
    config(['app.debug' => false]);

    $this->getJson('/api/v1/test-errors/crash')
        ->assertInternalServerError()
        ->assertExactJson(['message' => __('errors.server_error'), 'code' => 'server_error', 'errors' => []])
        ->assertDontSee('SQLSTATE');
});

it('includes debugging details for unexpected errors only when debugging is on', function (): void {
    config(['app.debug' => true]);

    $this->getJson('/api/v1/test-errors/crash')
        ->assertInternalServerError()
        ->assertJsonPath('debug.exception', RuntimeException::class);
});

it('returns 429 with a Retry-After header when a rate limit is exceeded', function (): void {
    $this->getJson('/api/v1/test-errors/throttled')->assertOk();

    $this->getJson('/api/v1/test-errors/throttled')
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

test('every built-in error code has an English message', function (string $code): void {
    expect(Lang::has('errors.'.$code, 'en'))->toBeTrue();
})->with([
    'bad_request', 'unauthenticated', 'forbidden', 'not_found', 'method_not_allowed', 'conflict',
    'validation_failed', 'too_many_requests', 'server_error', 'service_unavailable', 'http_error',
    'idempotency_key_missing', 'idempotency_key_reused', 'idempotent_request_in_progress',
]);

it('adds security headers to JSON responses', function (): void {
    $this->getJson('/api/v1/test-errors/nowhere')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
});
