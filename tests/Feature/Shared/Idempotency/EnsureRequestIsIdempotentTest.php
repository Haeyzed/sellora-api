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

it('keeps one requester\'s key from replaying to anyone else: other accounts, other guards, guests, or guests behind the same address', function (): void {
    $requesters = [
        'customer 1' => ['as' => ['customer', 1], 'guest' => null],
        'customer 2' => ['as' => ['customer', 2], 'guest' => null],
        'staff member 1' => ['as' => ['staff', 1], 'guest' => null],
        'customer 1 with a guest cart' => ['as' => ['customer', 1], 'guest' => 'guest-a'],
        'guest a' => ['as' => null, 'guest' => 'guest-a'],
        'guest b' => ['as' => null, 'guest' => 'guest-b'],
    ];

    foreach (['customer 1', 'customer 2', 'staff member 1', 'guest a', 'guest b'] as $charge => $who) {
        chargeWithKey('order-key-0007', ...$requesters[$who])->assertExactJson(['charge' => $charge + 1])->assertHeaderMissing('Idempotent-Replayed');
    }

    // Each gets their own answer back on retry; a signed-in customer is the customer, whatever cart token comes along.
    foreach (['customer 1' => 1, 'customer 2' => 2, 'staff member 1' => 3, 'customer 1 with a guest cart' => 1, 'guest a' => 4, 'guest b' => 5] as $who => $charge) {
        chargeWithKey('order-key-0007', ...$requesters[$who])->assertExactJson(['charge' => $charge])->assertHeader('Idempotent-Replayed', 'true');
    }

    expect($this->chargesCreated)->toBe(5)
        ->and(IdempotencyKey::query()->orderBy('id')->pluck('scope')->all())->toBe(['customer:1', 'customer:2', 'staff:1', 'cart:'.hash('sha256', str_pad('guest-a', 43, 'x')), 'cart:'.hash('sha256', str_pad('guest-b', 43, 'x'))]);
});

it('refuses a key from nobody known, rather than sharing it with everyone at the same address', function (?string $token): void {
    $this->withHeader('Idempotency-Key', 'order-key-0009')->withHeader('X-Cart-Token', (string) $token)->postJson('/api/v1/sample-charges', ['amount' => 1000])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'idempotency_requester_unknown');

    expect($this->chargesCreated)->toBe(0)
        ->and(IdempotencyKey::query()->count())->toBe(0);
})->with(['no cart token' => [null], 'a malformed cart token' => ['not-a-token']]);

it('stores the replayable response encrypted', function (): void {
    chargeWithKey('order-key-0008');

    $storedBody = DB::table('idempotency_keys')->value('response_body');

    expect($storedBody)->not->toContain('charge');
});
