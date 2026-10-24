<?php

declare(strict_types=1);

use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Integrations\Paystack\ManualTest\ReceivedWebhooks;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b.5: the manual test-mode run (paystack:manual-test) is for a
 * developer's own machine. It refuses any environment but local, CI, and any
 * key but a Paystack test secret key, and never prints the key. The real run
 * pays with Paystack's test card by hand, so it is never run here: only what
 * it refuses is. Webhooks it is asked to keep are kept only once their
 * signature checked out, only locally, and only while it runs.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    usePaystack();
    config(['paystack.manual_test.webhooks_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'paystack-webhooks-'.Str::lower(Str::random(12)), 'paystack.manual_test.in_ci' => false]);
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('paystack.manual_test.webhooks_path'));
    deleteAllStores();
});

it('runs only in the local environment, never in CI', function (string $environment, bool $inCi): void {
    app()['env'] = $environment;
    config(['paystack.manual_test.secret_key' => PAYSTACK_FAKE_TEST_KEY, 'paystack.manual_test.in_ci' => $inCi]);
    Http::fake();

    $this->artisan('paystack:manual-test', ['store' => $this->store->id, 'order' => 1])
        ->expectsOutputToContain('only in the local environment')
        ->assertFailed();

    Http::assertNothingSent();
    expect($this->store->run(static fn (): int => PaymentGatewayConnection::query()->count()))->toBe(0);
})->with([
    'testing' => ['testing', false],
    'production' => ['production', false],
    'local, in CI' => ['local', true],
]);

it('refuses any key but a Paystack test secret key, without printing it or sending it anywhere', function (?string $key): void {
    app()['env'] = 'local';
    config(['paystack.manual_test.secret_key' => $key]);
    Http::fake();

    $this->artisan('paystack:manual-test', ['store' => $this->store->id, 'order' => 1])
        ->expectsOutputToContain('must hold a Paystack test secret key')
        ->doesntExpectOutputToContain('fakepaystackkey')
        ->assertFailed();

    Http::assertNothingSent();
})->with([
    'a live key' => [PAYSTACK_FAKE_LIVE_KEY],
    'a public key' => ['pk_test_'.'fakepaystackkey0123456789'],
    'a test key with something after it' => [PAYSTACK_FAKE_TEST_KEY.' fakepaystackkey'],
    'none' => [null],
    'empty' => [''],
]);

it('keeps a Paystack webhook only once its signature checked out, only locally, and only while asked to', function (): void {
    connectPaystack();
    $webhooks = app(ReceivedWebhooks::class);
    $kept = static fn (): array => array_map(static fn (string $path): string => (string) file_get_contents($path), File::glob($webhooks->folder().DIRECTORY_SEPARATOR.'*.json'));
    app()['env'] = 'local';
    $webhooks->startSaving(CarbonImmutable::now()->addHour());

    sendPaystackWebhook('{"event":"transfer.success","n":1}')->assertOk();
    sendPaystackWebhook('{"event":"transfer.success","n":2}', PAYSTACK_FAKE_LIVE_KEY)->assertUnauthorized();

    expect($kept())->toBe(['{"event":"transfer.success","n":1}']);

    config(['paystack.manual_test.in_ci' => true]);
    sendPaystackWebhook('{"event":"transfer.success","n":3}')->assertOk();
    config(['paystack.manual_test.in_ci' => false]);
    app()['env'] = 'testing';
    sendPaystackWebhook('{"event":"transfer.success","n":4}')->assertOk();
    app()['env'] = 'local';
    $this->travel(61)->minutes();
    sendPaystackWebhook('{"event":"transfer.success","n":5}')->assertOk();
    $webhooks->startSaving(CarbonImmutable::now()->addHour());
    $webhooks->stopSaving();
    sendPaystackWebhook('{"event":"transfer.success","n":6}')->assertOk();

    expect($kept())->toBe(['{"event":"transfer.success","n":1}']);
});
