<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Idempotency\IdempotencyStatus;
use App\Shared\Retention\PurgeExpiredRecords;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 * Section 14: one store must never see or affect another store's data,
 * counters or background work. These tests use real store databases.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    $this->chargesCreated = 0;

    Route::middleware(['api', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
        ->prefix('api/v1')
        ->group(function (): void {
            Route::post('/sample-charges', function (): array {
                $this->chargesCreated++;

                return ['charge' => $this->chargesCreated];
            })->middleware('idempotent');

            Route::post('/sample-login', static fn (): string => 'ok')->middleware('throttle:login');
        });

    $this->firstStore = createStore('first-store');
    $this->secondStore = createStore('second-store');
});

afterEach(function (): void {
    deleteAllStores();
});

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

it('never replays one store\'s response to a request in another store using the same Idempotency-Key', function (): void {
    $this->withHeader('Idempotency-Key', 'shared-key-0001')
        ->postJson(storeUrl('first-store', '/api/v1/sample-charges'), ['amount' => 1000])
        ->assertExactJson(['charge' => 1]);
    tenancy()->end();

    $this->withHeader('Idempotency-Key', 'shared-key-0001')
        ->postJson(storeUrl('second-store', '/api/v1/sample-charges'), ['amount' => 1000])
        ->assertExactJson(['charge' => 2])
        ->assertHeaderMissing('Idempotent-Replayed');
});

it('does not let sign-in attempts in one store use up the limit in another store', function (): void {
    config(['api.rate_limits.login' => 2]);

    foreach ([1, 2] as $attempt) {
        $this->postJson(storeUrl('first-store', '/api/v1/sample-login'), ['email' => 'ada@example.com'])->assertOk();
        tenancy()->end();
    }
    $this->postJson(storeUrl('first-store', '/api/v1/sample-login'), ['email' => 'ada@example.com'])->assertTooManyRequests();
    tenancy()->end();

    $this->postJson(storeUrl('second-store', '/api/v1/sample-login'), ['email' => 'ada@example.com'])->assertOk();
});

it('purges expired records in one store without touching another store', function (): void {
    seedExpiredIdempotencyKey($this->firstStore, 'first-store-key');
    seedExpiredIdempotencyKey($this->secondStore, 'second-store-key');

    $this->firstStore->run(static fn () => PurgeExpiredRecords::dispatchSync());

    expect($this->firstStore->run(static fn (): int => IdempotencyKey::query()->count()))->toBe(0)
        ->and($this->secondStore->run(static fn (): int => IdempotencyKey::query()->count()))->toBe(1);
});

it('queues one purge job for the platform and one for each store', function (): void {
    Queue::fake([PurgeExpiredRecords::class]);

    $this->artisan('retention:purge')->assertSuccessful();

    Queue::assertPushed(PurgeExpiredRecords::class, 3);
});
