<?php

declare(strict_types=1);

use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Idempotency\IdempotencyStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->chargesCreated = 0;
    $this->shouldCrash = false;

    Route::post('/api/v1/sample-charges', function (): array {
        if ($this->shouldCrash) {
            throw new RuntimeException('Gateway timed out');
        }

        $this->chargesCreated++;

        return ['charge' => $this->chargesCreated];
    })->middleware('idempotent')->name('sample-charges.store');
});

function chargeWithKey(string $key, array $body = ['amount' => 1000], string $ip = '203.0.113.10'): Illuminate\Testing\TestResponse
{
    return test()->withHeader('Idempotency-Key', $key)
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/sample-charges', $body);
}

it('rejects a money request without an Idempotency-Key header', function (): void {
    $this->postJson('/api/v1/sample-charges', ['amount' => 1000])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_missing')
        ->assertJsonValidationErrors('Idempotency-Key');

    expect($this->chargesCreated)->toBe(0);
});

it('rejects a malformed Idempotency-Key header', function (): void {
    chargeWithKey('short')->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_missing');
});

it('processes a retried request once and replays the first response', function (): void {
    $first = chargeWithKey('order-key-0001')->assertOk()->assertExactJson(['charge' => 1]);

    chargeWithKey('order-key-0001')
        ->assertOk()
        ->assertExactJson(['charge' => 1])
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($this->chargesCreated)->toBe(1)
        ->and($first->headers->has('Idempotent-Replayed'))->toBeFalse();
});

it('treats the same body with keys in a different order as the same request', function (): void {
    chargeWithKey('order-key-0002', ['amount' => 1000, 'currency' => 'NGN']);

    chargeWithKey('order-key-0002', ['currency' => 'NGN', 'amount' => 1000])->assertHeader('Idempotent-Replayed', 'true');

    expect($this->chargesCreated)->toBe(1);
});

it('returns 422 when a key is reused for a different request', function (): void {
    chargeWithKey('order-key-0003', ['amount' => 1000]);

    chargeWithKey('order-key-0003', ['amount' => 999999])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_reused');

    expect($this->chargesCreated)->toBe(1);
});

it('returns 409 while the original request is still running', function (): void {
    chargeWithKey('order-key-0004');
    IdempotencyKey::query()->update([
        'status' => IdempotencyStatus::Processing,
        'locked_until' => now()->addMinute(),
    ]);

    chargeWithKey('order-key-0004')
        ->assertConflict()
        ->assertJsonPath('code', 'idempotent_request_in_progress');

    expect($this->chargesCreated)->toBe(1);
});

it('lets a retry take over when the original attempt crashed and its lease ran out', function (): void {
    chargeWithKey('order-key-0005');
    IdempotencyKey::query()->update([
        'status' => IdempotencyStatus::Processing,
        'locked_until' => now()->subSecond(),
    ]);

    chargeWithKey('order-key-0005')->assertOk()->assertExactJson(['charge' => 2]);
});

it('frees the key after a server error so the client can retry', function (): void {
    $this->shouldCrash = true;
    chargeWithKey('order-key-0006')->assertInternalServerError();

    $this->shouldCrash = false;
    chargeWithKey('order-key-0006')->assertOk()->assertExactJson(['charge' => 1]);
});

it('keeps keys from different requesters apart', function (): void {
    chargeWithKey('order-key-0007', ip: '203.0.113.10')->assertExactJson(['charge' => 1]);

    chargeWithKey('order-key-0007', ip: '198.51.100.20')
        ->assertExactJson(['charge' => 2])
        ->assertHeaderMissing('Idempotent-Replayed');
});

it('stores the replayable response encrypted', function (): void {
    chargeWithKey('order-key-0008');

    $storedBody = DB::table('idempotency_keys')->value('response_body');

    expect($storedBody)->not->toContain('charge');
});
