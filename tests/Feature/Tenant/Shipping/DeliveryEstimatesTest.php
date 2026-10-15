<?php

declare(strict_types=1);

use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 13: when an order would arrive. A method says how many business days
 * it takes, at least and at most; the dates count only the store's working
 * days (Monday to Friday until changed), from today in the store's
 * timezone. No holidays yet.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shipping-store', ['country_code' => 'NG', 'timezone' => 'Africa/Lagos']);

    [$this->owner, $this->parcel] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $parcel = cartVariant(price: 100000, name: 'Lamp');
        $parcel->forceFill(['requires_shipping' => true, 'weight_grams' => 400])->save();

        return [$owner, $parcel->public_id];
    });

    $this->zone = shippingZone('Nigeria', ['NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('counts business days from today on the store\'s working days', function (): void {
    methodTaking(2, 4);
    methodTaking(0, 1, 'Same day');
    methodTaking(null, null, 'Whenever');

    // Wednesday 14 October, mid-morning in Lagos.
    $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00', 'UTC'));

    expect(estimatesNow())->toBe([
        'Standard' => ['min_days' => 2, 'max_days' => 4, 'earliest' => '2026-10-16', 'latest' => '2026-10-20'],
        'Same day' => ['min_days' => 0, 'max_days' => 1, 'earliest' => '2026-10-14', 'latest' => '2026-10-15'],
        'Whenever' => null,
    ]);

    // Working Saturdays too: the fourth day is Monday, not Tuesday.
    changeWorkingDays([6, 1, 2, 3, 4, 5])->assertOk()->assertJsonPath('data.working_days', [1, 2, 3, 4, 5, 6]);
    expect(estimatesNow()['Standard'])->toMatchArray(['earliest' => '2026-10-16', 'latest' => '2026-10-19']);
});

it('counts from today in the store\'s timezone, not the server\'s', function (): void {
    methodTaking(0, 1, 'Same day');

    // Friday 23:30 in UTC is already Saturday 00:30 in Lagos, so nothing goes before Monday.
    $this->travelTo(CarbonImmutable::parse('2026-10-16 23:30', 'UTC'));

    expect(estimatesNow()['Same day'])->toMatchArray(['earliest' => '2026-10-19', 'latest' => '2026-10-20']);
});

it('shows the chosen method\'s estimate in the cart', function (): void {
    $method = methodTaking(1, 3);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00', 'UTC'));

    $token = newGuestCart('shipping-store');
    cartRequest('POST', '/lines', ['variant' => $this->parcel], token: $token, subdomain: 'shipping-store')->assertOk();

    cartRequest('PATCH', '', ['shipping_method' => $method], token: $token, ifMatch: '"2"', subdomain: 'shipping-store')
        ->assertOk()
        ->assertJsonPath('data.shipping.estimate', ['min_days' => 1, 'max_days' => 3, 'earliest' => '2026-10-15', 'latest' => '2026-10-19']);
});

it('takes a method\'s days as a pair, the most no fewer than the least, within a year', function (array $days, string $error): void {
    shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Bad'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 1]], ...$days])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'the least without the most' => [['min_days' => 2], 'max_days'],
    'the most without the least' => [['max_days' => 2], 'min_days'],
    'the most below the least' => [['min_days' => 3, 'max_days' => 2], 'max_days'],
    'more than a year' => [['min_days' => 1, 'max_days' => 366], 'max_days'],
    'negative' => [['min_days' => -1, 'max_days' => 2], 'min_days'],
]);

it('takes working days as distinct ISO weekday numbers, at least one, and audits the change', function (array $days): void {
    changeWorkingDays($days)->assertUnprocessable();
})->with([
    'none' => [[]],
    'zero' => [[0, 1]],
    'eight' => [[1, 8]],
    'twice' => [[1, 1]],
    'as text' => [['1']],
]);

it('audits a change of working days with the store\'s settings', function (): void {
    changeWorkingDays([1, 2, 3, 4, 5, 6])->assertOk();

    $audited = $this->store->run(static fn (): array => Audit::query()->where('auditable_type', 'store_settings')->latest('id')->firstOrFail()->new_values);

    expect(json_decode((string) $audited['working_days'], true))->toBe([1, 2, 3, 4, 5, 6]);
});

it('keeps days as a pair, and at least one working day, in the database too', function (): void {
    methodTaking(1, 2);

    $this->store->run(static function (): void {
        expect(static fn () => DB::table('shipping_methods')->update(['max_days' => null]))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('shipping_methods')->update(['min_days' => 3]))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('shipping_methods')->update(['max_days' => 366]))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('store_settings')->update(['working_days' => '[]']))->toThrow(QueryException::class);
    });
});
