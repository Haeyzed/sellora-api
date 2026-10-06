<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Exceptions\FeatureNotEnabledException;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Shared\Features\FeatureSnapshot;
use App\Shared\Features\FeatureState;
use Carbon\CarbonImmutable;
use Tests\Fixtures\Features\FakeFeatureSource;

beforeEach(function (): void {
    $registry = new FeatureRegistry;
    $registry->register(new FeatureDefinition('loyalty', FeatureKind::Module));
    $registry->register(new FeatureDefinition('loyalty_tiers', FeatureKind::Module, requiredFeatureKeys: ['loyalty']));
    $registry->register(new FeatureDefinition('whatsapp', FeatureKind::Integration));
    app()->instance(FeatureRegistry::class, $registry);

    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
});

function features(): Features
{
    return app(Features::class);
}

it('gives each feature the state its plan, history and merchant settings call for', function (FeatureSnapshot $snapshot, FeatureState $expectedState): void {
    $this->featureSource->give('store-a', $snapshot);

    expect(features()->stateFor('store-a', 'loyalty'))->toBe($expectedState);
})->with([
    'on the plan' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty']), FeatureState::Enabled],
    'on the plan but switched off by the merchant' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty'], merchantDisabled: ['loyalty']), FeatureState::Disabled],
    'removed by a downgrade' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(removed: ['loyalty']), FeatureState::Locked],
    'back on the plan after an earlier downgrade' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty'], removed: ['loyalty']), FeatureState::Enabled],
    'never on the plan' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(), FeatureState::Unavailable],
    'store suspended' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty'], isStoreSuspended: true), FeatureState::Suspended],
]);

it('never makes a feature more open than the feature it depends on', function (FeatureSnapshot $snapshot, FeatureState $expectedState): void {
    $this->featureSource->give('store-a', $snapshot);

    expect(features()->stateFor('store-a', 'loyalty_tiers'))->toBe($expectedState);
})->with([
    'requirement locked' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty_tiers'], removed: ['loyalty']), FeatureState::Locked],
    'requirement missing from the plan' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty_tiers']), FeatureState::Unavailable],
    'requirement switched off' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty', 'loyalty_tiers'], merchantDisabled: ['loyalty']), FeatureState::Disabled],
    'both enabled' => [fn (): FeatureSnapshot => FakeFeatureSource::snapshot(entitled: ['loyalty', 'loyalty_tiers']), FeatureState::Enabled],
]);

it('refuses features that depend on each other in a loop', function (): void {
    $registry = new FeatureRegistry;
    $registry->register(new FeatureDefinition('first', FeatureKind::Module, requiredFeatureKeys: ['second']));
    $registry->register(new FeatureDefinition('second', FeatureKind::Module, requiredFeatureKeys: ['first']));
    app()->instance(FeatureRegistry::class, $registry);

    expect(fn (): FeatureState => features()->stateFor('store-a', 'first'))->toThrow(LogicException::class, 'depends on itself');
});

it('refuses a feature key that no module or integration uses', function (): void {
    expect(fn (): FeatureState => features()->stateFor('store-a', 'lyalty'))->toThrow(LogicException::class);
});

it('refuses registering two features with the same key', function (): void {
    expect(fn () => app(FeatureRegistry::class)->register(new FeatureDefinition('loyalty', FeatureKind::Module)))->toThrow(LogicException::class);
});

it('treats every feature as unavailable and every limit as zero outside a store', function (): void {
    expect(features()->state('loyalty'))->toBe(FeatureState::Unavailable)
        ->and(features()->limit('products'))->toBe(0);
});

it('reports the plan limit, unlimited as null, and zero for a limit the plan forgot', function (): void {
    $this->featureSource->give('store-a', FakeFeatureSource::snapshot(limits: ['products' => 250, 'locations' => null]));

    expect(features()->limitFor('store-a', 'products'))->toBe(250)
        ->and(features()->limitFor('store-a', 'locations'))->toBeNull()
        ->and(features()->limitFor('store-a', 'staff_accounts'))->toBe(0);
});

it('refuses a limit key that is not listed in config', function (): void {
    expect(fn (): ?int => features()->limitFor('store-a', 'prodcts'))->toThrow(LogicException::class);
});

it('caches each store\'s plan so feature checks don\'t ask the platform every time', function (): void {
    $this->featureSource->give('store-a', FakeFeatureSource::snapshot(entitled: ['loyalty']));

    features()->stateFor('store-a', 'loyalty');
    features()->stateFor('store-a', 'whatsapp');
    features()->limitFor('store-a', 'products');

    expect($this->featureSource->timesAsked)->toBe(1);
});

it('reads a store\'s plan fresh after its cache is cleared', function (): void {
    $this->featureSource->give('store-a', FakeFeatureSource::snapshot(entitled: ['loyalty']));
    features()->stateFor('store-a', 'loyalty');
    $this->featureSource->give('store-a', FakeFeatureSource::snapshot(removed: ['loyalty']));

    features()->forget('store-a');

    expect(features()->stateFor('store-a', 'loyalty'))->toBe(FeatureState::Locked);
});

it('stops trusting the cache when a temporary change in the plan is due', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00'));
    $this->featureSource->give('store-a', new FeatureSnapshot(false, ['loyalty'], [], [], ['products' => 1000], changesAt: CarbonImmutable::parse('2026-10-06 10:01:00')));
    features()->limitFor('store-a', 'products');
    $this->featureSource->give('store-a', FakeFeatureSource::snapshot(limits: ['products' => 250]));

    $this->travelTo(CarbonImmutable::parse('2026-10-06 10:01:01'));

    expect(features()->limitFor('store-a', 'products'))->toBe(250);
});

it('keeps each store\'s plan separate, even after one store\'s plan is cached', function (): void {
    $this->featureSource->give('store-a', FakeFeatureSource::snapshot(entitled: ['loyalty'], limits: ['products' => 5000]));
    $this->featureSource->give('store-b', FakeFeatureSource::snapshot(limits: ['products' => 10]));

    features()->stateFor('store-a', 'loyalty');

    expect(features()->stateFor('store-b', 'loyalty'))->toBe(FeatureState::Unavailable)
        ->and(features()->limitFor('store-b', 'products'))->toBe(10);
});

it('raises a translated 403 error naming why the feature can\'t be used', function (): void {
    $exception = new FeatureNotEnabledException('loyalty', FeatureState::Locked);

    expect($exception->errorCode())->toBe('feature_locked')
        ->and($exception->status())->toBe(403)
        ->and($exception->translatedMessage())->toBe(__('errors.feature_locked'));
});

it('raises a translated 403 error with the limit when a store has used all its plan allows', function (): void {
    $exception = new UsageLimitReachedException('products', 250);

    expect($exception->status())->toBe(403)
        ->and($exception->translatedMessage())->toBe('Your plan allows up to 250 of these. Upgrade your plan to add more.');
});
