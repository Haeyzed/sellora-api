<?php

declare(strict_types=1);

use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Cart\Http\Middleware\AuthenticateCustomerIfSignedIn;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 14: one store must never see or affect another store's data,
 * counters or background work. These tests use real store databases.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    $this->chargesCreated = 0;

    Route::middleware(['api', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
        ->prefix('api/v1')
        ->group(function (): void {
            Route::post('/sample-charges', function (): array {
                $this->chargesCreated++;

                return ['charge' => $this->chargesCreated];
            })->middleware([AuthenticateCustomerIfSignedIn::class, 'idempotent']);

            Route::post('/sample-login', static fn (): string => 'ok')->middleware('throttle:login');
        });

    $this->firstStore = createStore('first-store');
    $this->secondStore = createStore('second-store');
});

afterEach(function (): void {
    deleteAllStores();
});

it('never replays one store\'s response to a request in another store using the same Idempotency-Key', function (): void {
    $guestToken = str_repeat('g', 43);

    $this->withHeaders(['Idempotency-Key' => 'shared-key-0001', 'X-Cart-Token' => $guestToken])
        ->postJson(storeUrl('first-store', '/api/v1/sample-charges'), ['amount' => 1000])
        ->assertExactJson(['charge' => 1]);
    tenancy()->end();

    $this->withHeaders(['Idempotency-Key' => 'shared-key-0001', 'X-Cart-Token' => $guestToken])
        ->postJson(storeUrl('second-store', '/api/v1/sample-charges'), ['amount' => 1000])
        ->assertExactJson(['charge' => 2])
        ->assertHeaderMissing('Idempotent-Replayed');
});

it('never replays one shopper\'s response to another shopper in the same store sending the same Idempotency-Key', function (): void {
    [$ada, $bayo] = $this->firstStore->run(static fn (): array => [Customer::factory()->create(), Customer::factory()->create()]);
    $send = function (?Customer $customer, ?string $guestToken) {
        forgetSignIns();
        $request = $this->withHeaders(array_filter(['Idempotency-Key' => 'shared-key-0002', 'X-Cart-Token' => $guestToken]));
        $request = $customer === null ? $request : $request->withToken(customerTokenFor($customer, 'first-store'));
        $response = $request->postJson(storeUrl('first-store', '/api/v1/sample-charges'), ['amount' => 1000]);
        $this->flushHeaders();
        tenancy()->end();

        return $response;
    };

    $send($ada, null)->assertExactJson(['charge' => 1]);
    $send($bayo, null)->assertExactJson(['charge' => 2])->assertHeaderMissing('Idempotent-Replayed');
    $send(null, str_repeat('g', 43))->assertExactJson(['charge' => 3])->assertHeaderMissing('Idempotent-Replayed');
    $send(null, str_repeat('h', 43))->assertExactJson(['charge' => 4])->assertHeaderMissing('Idempotent-Replayed');

    $send($ada, null)->assertExactJson(['charge' => 1])->assertHeader('Idempotent-Replayed', 'true');
    $send($bayo, null)->assertExactJson(['charge' => 2])->assertHeader('Idempotent-Replayed', 'true');
    $send(null, str_repeat('g', 43))->assertExactJson(['charge' => 3])->assertHeader('Idempotent-Replayed', 'true');
    expect($this->chargesCreated)->toBe(4);
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
