<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Domain;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;

/*
 * Section 9.1: a store is identified by the exact host it registered with,
 * so when the platform domain changes, every store's subdomain must move to
 * the new one or the store becomes unreachable. Custom domains never move.
 */
uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('platform.domain', 'sellora-api.test');
});

function storeWithDomain(string $domain): Tenant
{
    $store = Tenant::factory()->create();
    $store->domains()->create(['domain' => $domain]);

    return $store;
}

it('moves every store subdomain on the old platform domain to the current one, and only those', function (): void {
    $moved = storeWithDomain('shoprite-nigeria.sellora.test');
    $alreadyCurrent = storeWithDomain('berlin-fashion.sellora-api.test');
    storeWithDomain('shop.notsellora.test');
    storeWithDomain('deep.shop.sellora.test');
    storeWithDomain('adasellora.test');

    expect(Artisan::call('stores:move-platform-domain', ['from' => 'sellora.test']))->toBe(0)
        ->and(Domain::query()->orderBy('id')->pluck('domain')->all())->toBe([
            'shoprite-nigeria.sellora-api.test',
            'berlin-fashion.sellora-api.test',
            'shop.notsellora.test',
            'deep.shop.sellora.test',
            'adasellora.test',
        ])
        ->and(Activity::query()->where('event', 'store_domain_moved')->sole()->subject?->is($moved))->toBeTrue()
        ->and($alreadyCurrent->domains()->value('domain'))->toBe('berlin-fashion.sellora-api.test');

    expect(Artisan::call('stores:move-platform-domain', ['from' => 'sellora.test']))->toBe(0)
        ->and(Artisan::output())->toContain('No store subdomain is on sellora.test');
});

it('changes nothing on a dry run, or when a new address is already taken', function (): void {
    storeWithDomain('ada.sellora.test');
    storeWithDomain('bola.sellora.test');

    expect(Artisan::call('stores:move-platform-domain', ['from' => 'sellora.test', '--dry-run' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('ada.sellora-api.test')->toContain('Dry run')
        ->and(Domain::query()->where('domain', 'like', '%.sellora.test')->count())->toBe(2);

    storeWithDomain('bola.sellora-api.test');

    expect(Artisan::call('stores:move-platform-domain', ['from' => 'sellora.test']))->toBe(1)
        ->and(Artisan::output())->toContain('bola.sellora-api.test')
        ->and(Domain::query()->where('domain', 'like', '%.sellora.test')->count())->toBe(2);
});

it('refuses to move from the current platform domain', function (): void {
    expect(Artisan::call('stores:move-platform-domain', ['from' => 'sellora-api.test']))->toBe(1);
});
