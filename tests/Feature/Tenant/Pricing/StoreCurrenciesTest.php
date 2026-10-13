<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Pricing\Services\StoreCurrencyLimit;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 3.3: a store sells in its base currency and, within its plan's
 * presentment_currencies limit, in others. Each converts at the market rate
 * plus the store's margin (0 to 25%), kept apart, or at a manual rate. A
 * currency whose market rate is over 48 hours old, with no manual rate, isn't
 * offered. Everything is audited.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('currency-store');
    $this->featureSource = (new FakeFeatureSource)->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['presentment_currencies' => 2]));
    app()->instance(FeatureSource::class, $this->featureSource);

    [$this->owner, $this->viewer] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $role = Role::findOrCreate('Accountant', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('pricing.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        return [$owner, $viewer];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('converts at the market rate plus the store\'s margin, shown beside the market rate', function (): void {
    marketRates(['NGN' => '1500', 'EUR' => '0.9']);

    currencyRequest('POST', '', ['currency' => 'USD', 'margin_percent' => '2.5'])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.margin_percent', '2.5000')
        // 1 NGN = 1/1500 USD, × 1.025.
        ->assertJsonPath('data.market_rate.rate', '0.000666666666666667')
        ->assertJsonPath('data.applied_rate.rate', '0.000683333333333334')
        ->assertJsonPath('data.applied_rate.source', 'market')
        ->assertJsonPath('data.is_offered', true);

    currencyRequest('POST', '', ['currency' => 'EUR'])->assertCreated()->assertJsonPath('data.applied_rate.rate', '0.000600000000000000');
    currencyRequest('GET', '')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.currency', 'EUR');

    // What an order will save: the rate used, and the market rate and margin it came from, kept apart.
    $this->store->run(static function (): void {
        $rates = app(FindExchangeRates::class)->handle(['USD', 'NGN', 'JPY']);

        expect(array_keys($rates))->toEqualCanonicalizing(['USD', 'NGN'])
            ->and($rates['USD']->rate->toString())->toBe('0.000683333333333334')
            ->and($rates['USD']->marketRate?->toString())->toBe('0.000666666666666667')
            ->and($rates['USD']->marginPercent->toString())->toBe('2.5000')
            ->and($rates['USD']->source)->toBe(ExchangeRateSource::Market)
            ->and($rates['NGN']->source)->toBe(ExchangeRateSource::Base)
            ->and($rates['NGN']->rate->toString())->toBe('1');
    });
});

it('uses a manual rate instead, without the margin, and goes back to the market rate when it is removed', function (): void {
    marketRates(['NGN' => '1500']);
    currencyRequest('POST', '', ['currency' => 'USD', 'margin_percent' => '5'])->assertCreated();

    currencyRequest('PUT', '/USD/rate', ['rate' => '0.00065'])
        ->assertOk()
        ->assertJsonPath('data.manual_rate', '0.000650000000')
        ->assertJsonPath('data.applied_rate.rate', '0.00065')
        ->assertJsonPath('data.applied_rate.source', 'manual')
        ->assertJsonPath('data.market_rate.rate', '0.000666666666666667');

    foreach (['0', '-1', 'abc', 0.0007, '1e-3', '0.0000000000001'] as $invalid) {
        currencyRequest('PUT', '/USD/rate', ['rate' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('rate');
    }

    currencyRequest('DELETE', '/USD/rate')->assertOk()->assertJsonPath('data.manual_rate', null)->assertJsonPath('data.applied_rate.source', 'market');
});

it('stops offering a currency whose market rate is over 48 hours old, unless a manual rate stands in', function (): void {
    marketRates(['NGN' => '1500', 'EUR' => '0.9'], hoursAgo: 49);
    currencyRequest('POST', '', ['currency' => 'USD'])->assertCreated()->assertJsonPath('data.is_stale', true)->assertJsonPath('data.is_offered', false)->assertJsonPath('data.applied_rate', null);

    storefrontCurrencies()->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.currency', 'NGN');

    currencyRequest('PUT', '/USD/rate', ['rate' => '0.0007'])->assertOk()->assertJsonPath('data.is_stale', false)->assertJsonPath('data.is_offered', true);

    // A fresh fetch brings the market back.
    currencyRequest('DELETE', '/USD/rate')->assertJsonPath('data.is_offered', false);
    marketRates(['NGN' => '1500'], hoursAgo: 47);
    currencyRequest('GET', '/USD')->assertJsonPath('data.is_stale', false)->assertJsonPath('data.is_offered', true);
});

it('shows customers the base currency first and only what they can buy in now, never rates', function (): void {
    marketRates(['NGN' => '1500', 'EUR' => '0.9']);
    currencyRequest('POST', '', ['currency' => 'USD']);
    currencyRequest('POST', '', ['currency' => 'EUR']);
    currencyRequest('PATCH', '/EUR', ['is_active' => false])->assertOk()->assertJsonPath('data.is_offered', false);

    $currencies = storefrontCurrencies()->assertOk()->assertJsonPath('meta.total', 2);
    expect($currencies->json('data'))->toBe([['currency' => 'NGN', 'is_base' => true], ['currency' => 'USD', 'is_base' => false]])
        ->and($currencies->getContent())->not->toContain('rate')->not->toContain('margin');

    storefrontCurrencies('?per_page=1&page=2')->assertJsonPath('data.0.currency', 'USD')->assertJsonPath('meta.last_page', 2);
});

it('counts only active currencies, never the base one, towards the plan\'s limit', function (): void {
    currencyRequest('POST', '', ['currency' => 'NGN'])->assertUnprocessable()->assertJsonPath('code', 'currency_is_base');
    currencyRequest('POST', '', ['currency' => 'USD'])->assertCreated();
    currencyRequest('POST', '', ['currency' => 'USD'])->assertConflict()->assertJsonPath('code', 'store_currency_exists');
    currencyRequest('POST', '', ['currency' => 'EUR'])->assertCreated();
    currencyRequest('POST', '', ['currency' => 'JPY'])->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');

    currencyRequest('PATCH', '/EUR', ['is_active' => false])->assertOk();
    currencyRequest('POST', '', ['currency' => 'JPY'])->assertCreated();
    currencyRequest('PATCH', '/EUR', ['is_active' => true])->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');

    // A plan without the limit allows none.
    $this->featureSource->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot());
    app(App\Shared\Features\Features::class)->forget((string) $this->store->getTenantKey());
    currencyRequest('PATCH', '/JPY', ['is_active' => false]);
    currencyRequest('PATCH', '/JPY', ['is_active' => true])->assertForbidden();
});

it('counts turning a currency back on as adding one, so a store over its limit after a downgrade can\'t', function (): void {
    $this->featureSource->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['presentment_currencies' => 3]));
    app(App\Shared\Features\Features::class)->forget((string) $this->store->getTenantKey());

    foreach (['USD', 'EUR', 'JPY'] as $currency) {
        currencyRequest('POST', '', ['currency' => $currency])->assertCreated();
    }

    // The plan drops to one extra currency: nothing is turned off for the store.
    $this->featureSource->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['presentment_currencies' => 1]));
    app(App\Shared\Features\Features::class)->forget((string) $this->store->getTenantKey());

    currencyRequest('PATCH', '/JPY', ['is_active' => false])->assertOk();
    currencyRequest('PATCH', '/JPY', ['is_active' => true])->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');
    currencyRequest('POST', '', ['currency' => 'BTN'])->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');

    // Changing what a store already sells in is not adding one.
    currencyRequest('PATCH', '/USD', ['margin_percent' => '3', 'is_active' => true])->assertOk();

    expect($this->store->run(static fn (): array => StoreCurrency::query()->orderBy('currency_code')->pluck('is_active', 'currency_code')->all()))
        ->toBe(['EUR' => true, 'JPY' => false, 'USD' => true]);
});

it('checks the currency limit only inside a transaction, where its lock lasts until the change is saved', function (): void {
    $this->store->run(static function (): void {
        expect(fn () => app(StoreCurrencyLimit::class)->ensureRoomForOneMore())->toThrow(LogicException::class);
    });
});

it('takes a margin from 0 to 25 percent as a string with up to 4 decimals', function (): void {
    currencyRequest('POST', '', ['currency' => 'USD', 'margin_percent' => '25'])->assertCreated();
    currencyRequest('PATCH', '/USD', ['margin_percent' => '0'])->assertOk()->assertJsonPath('data.margin_percent', '0.0000');

    foreach (['-1', '25.0001', '26', 2.5, '2.55555', 'abc'] as $invalid) {
        currencyRequest('PATCH', '/USD', ['margin_percent' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('margin_percent');
    }

    currencyRequest('POST', '', ['currency' => 'XXY'])->assertJsonValidationErrors('currency');
});

it('audits margins and manual rates, and a manual rate locks the base currency', function (): void {
    currencyRequest('POST', '', ['currency' => 'USD', 'margin_percent' => '1']);
    currencyRequest('PATCH', '/USD', ['margin_percent' => '3']);
    expect($this->store->run(static fn (): bool => app(PricedRecordsRegistry::class)->anyExist()))->toBeFalse();

    currencyRequest('PUT', '/USD/rate', ['rate' => '0.0007']);

    $this->store->run(static function (): void {
        $audits = Audit::query()->where('auditable_type', 'store_currency')->orderBy('id')->get();
        expect($audits)->toHaveCount(3)
            ->and($audits[1]->new_values)->toBe(['margin_percent' => '3.0000'])
            ->and($audits[2]->new_values)->toHaveKey('manual_rate')
            ->and(app(PricedRecordsRegistry::class)->anyExist())->toBeTrue();
    });
});

it('lets pricing.view look but not change, and keeps each store\'s currencies to itself', function (): void {
    currencyRequest('POST', '', ['currency' => 'USD'])->assertCreated();

    currencyRequest('GET', '', as: $this->viewer)->assertOk();
    currencyRequest('POST', '', ['currency' => 'EUR'], as: $this->viewer)->assertForbidden();
    currencyRequest('PATCH', '/USD', ['is_active' => false], as: $this->viewer)->assertForbidden();
    currencyRequest('PUT', '/USD/rate', ['rate' => '0.0007'], as: $this->viewer)->assertForbidden();
    currencyRequest('DELETE', '/USD/rate', as: $this->viewer)->assertForbidden();

    $other = createStore('other-currency-store');
    $otherOwner = $other->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $token = $other->run(static fn (): string => app(AccessTokenIssuer::class)->issue($otherOwner, StaffMember::GUARD, 'test')->plainTextToken);
    forgetSignIns();

    $this->withToken($token)->getJson(storeUrl('other-currency-store', '/api/v1/staff/pricing/currencies/USD'))->assertNotFound();
    tenancy()->end();
    $this->getJson(storeUrl('other-currency-store', '/api/v1/catalog/currencies'))->assertJsonPath('meta.total', 1);
    tenancy()->end();
});
