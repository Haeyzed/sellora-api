<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Tenant\Messaging\MessageChannels;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Messaging\FakeTextChannel;

/*
|--------------------------------------------------------------------------
| For the Messaging tests (Step 20)
|--------------------------------------------------------------------------
*/

/**
 * A store for the messaging tests. Sets test()->store.
 */
function messagingStore(string $subdomain = 'messaging-store'): Tenant
{
    $store = createStore($subdomain, ['country_code' => 'NG']);
    test()->store = $store;

    return $store;
}

/**
 * Registers the fake text channel under its integration's feature key and puts that integration in the state given for test()->store ("enabled", "locked", "disabled", "suspended" or "unavailable").
 */
function useFakeTextChannel(string $state): void
{
    $features = app(FeatureRegistry::class);

    if (! $features->has(FakeTextChannel::FEATURE)) {
        $features->register(new FeatureDefinition(FakeTextChannel::FEATURE, FeatureKind::Integration));
    }

    FakeTextChannel::$sent = [];
    app(MessageChannels::class)->add(FakeTextChannel::class, FakeTextChannel::FEATURE);

    $source = app(FeatureSource::class);

    if (! $source instanceof FakeFeatureSource) {
        throw new LogicException('The channel tests need the fake feature source.');
    }

    $integration = FakeTextChannel::FEATURE;
    $source->give(test()->store->getTenantKey(), match ($state) {
        'enabled' => FakeFeatureSource::snapshot(entitled: [$integration]),
        'locked' => FakeFeatureSource::snapshot(removed: [$integration]),
        'disabled' => FakeFeatureSource::snapshot(entitled: [$integration], merchantDisabled: [$integration]),
        'suspended' => FakeFeatureSource::snapshot(entitled: [$integration], isStoreSuspended: true),
        'unavailable' => FakeFeatureSource::snapshot(),
    });
    app(Features::class)->forget(test()->store->getTenantKey());
}

/**
 * What the callback returns while the application's language is the one given, as a message is written when it is sent.
 *
 * @template TResult
 *
 * @param  Closure(): TResult  $write
 * @return TResult
 */
function writtenIn(string $locale, Closure $write): mixed
{
    $previous = app()->getLocale();
    app()->setLocale($locale);

    try {
        return $write();
    } finally {
        app()->setLocale($previous);
    }
}
