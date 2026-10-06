<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Shared\Features\FeatureSnapshot;
use App\Shared\Tenancy\TenantRoutes;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Features\SampleLoyaltyJob;
use Tests\Fixtures\Features\SampleLoyaltyModuleServiceProvider;

uses(DatabaseTruncation::class);

beforeEach(function (): void {
    app()->register(SampleLoyaltyModuleServiceProvider::class);

    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);

    $this->firstStore = createStore('first-store');
    SampleLoyaltyJob::$timesRun = 0;
});

afterEach(function (): void {
    deleteAllStores();
});

function giveFirstStore(FeatureSnapshot $snapshot): void
{
    test()->featureSource->give((string) test()->firstStore->getTenantKey(), $snapshot);
}

it('serves every module route when the module is enabled', function (): void {
    giveFirstStore(FakeFeatureSource::snapshot(entitled: ['loyalty']));

    $this->getJson(storeUrl('first-store', '/api/v1/loyalty-points'))->assertOk();
    tenancy()->end();
    $this->postJson(storeUrl('first-store', '/api/v1/loyalty-points'))->assertOk();
});

it('lets a locked module\'s data be read but returns 403 for changes', function (): void {
    giveFirstStore(FakeFeatureSource::snapshot(removed: ['loyalty']));

    $this->getJson(storeUrl('first-store', '/api/v1/loyalty-points'))->assertOk();
    tenancy()->end();
    $this->postJson(storeUrl('first-store', '/api/v1/loyalty-points'))
        ->assertForbidden()
        ->assertJsonPath('code', 'feature_locked');
});

it('keeps wind-down routes open while a module is locked or the store is suspended', function (FeatureSnapshot $snapshot): void {
    giveFirstStore($snapshot);

    $this->postJson(storeUrl('first-store', '/api/v1/loyalty-points/redeem'))->assertOk();
})->with([
    'locked' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(removed: ['loyalty'])],
    'suspended' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty'], isStoreSuspended: true)],
]);

it('returns 403 with the reason when the module can\'t be used at all', function (FeatureSnapshot $snapshot, string $expectedCode): void {
    giveFirstStore($snapshot);

    $this->getJson(storeUrl('first-store', '/api/v1/loyalty-points'))
        ->assertForbidden()
        ->assertJsonPath('code', $expectedCode);
})->with([
    'store suspended' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty'], isStoreSuspended: true), 'feature_suspended'],
    'switched off by the merchant' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty'], merchantDisabled: ['loyalty']), 'feature_disabled'],
    'not on the plan' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(), 'feature_unavailable'],
]);

it('returns 403 to a store without the module even when another store has it', function (): void {
    createStore('second-store');
    giveFirstStore(FakeFeatureSource::snapshot(entitled: ['loyalty']));

    $this->getJson(storeUrl('first-store', '/api/v1/loyalty-points'))->assertOk();
    tenancy()->end();
    $this->getJson(storeUrl('second-store', '/api/v1/loyalty-points'))
        ->assertForbidden()
        ->assertJsonPath('code', 'feature_unavailable');
});

it('names module routes after the module key', function (): void {
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('loyalty.points.index'))->toBeTrue();
});

it('fails loudly when a module is guarded with the integration middleware', function (): void {
    giveFirstStore(FakeFeatureSource::snapshot(entitled: ['loyalty']));
    Route::middleware([...TenantRoutes::MIDDLEWARE, 'integration:loyalty'])->get('/api/v1/misguarded', static fn (): string => 'open');

    $this->getJson(storeUrl('first-store', '/api/v1/misguarded'))->assertInternalServerError();
});

it('drops a module\'s queued job when the store can no longer use the module', function (FeatureSnapshot $snapshot, int $expectedRuns): void {
    giveFirstStore($snapshot);

    $this->firstStore->run(static fn () => SampleLoyaltyJob::dispatchSync());

    expect(SampleLoyaltyJob::$timesRun)->toBe($expectedRuns);
})->with([
    'enabled' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty']), 1],
    'locked by a downgrade after dispatch' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(removed: ['loyalty']), 0],
]);

it('blocks creating one more of something once the store uses all its plan allows', function (): void {
    giveFirstStore(FakeFeatureSource::snapshot(limits: ['products' => 250]));

    $this->firstStore->run(static function (): void {
        app(Features::class)->ensureWithinLimit('products', 249);

        expect(fn () => app(Features::class)->ensureWithinLimit('products', 250))->toThrow(UsageLimitReachedException::class);
    });
});
