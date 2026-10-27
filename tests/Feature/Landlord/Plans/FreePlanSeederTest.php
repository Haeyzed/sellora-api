<?php

declare(strict_types=1);

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Tenancy\Services\StoreRegistrationRequirements;
use Database\Seeders\Landlord\FreePlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

/*
 * Section 9.1: new stores start on the "free" plan, with the approved
 * starter limits (one presentment currency, section 3.3) and no paid modules.
 */
uses(LazilyRefreshDatabase::class);

it('creates the plan new stores start on, with the approved limits and no paid modules, and can run again', function (): void {
    $this->seed(FreePlanSeeder::class);
    Plan::query()->sole()->limits()->create(['limit_key' => 'locations', 'limit_value' => 5]);
    $this->seed(FreePlanSeeder::class);

    $plan = app(StoreRegistrationRequirements::class)->startingPlan();

    expect(Plan::query()->count())->toBe(1)
        ->and($plan->features()->count())->toBe(0)
        ->and($plan->limits()->pluck('limit_value', 'limit_key')->all())->toEqualCanonicalizing([
            'products' => 100,
            'staff_accounts' => 2,
            'storage_in_megabytes' => 1024,
            'presentment_currencies' => 1,
            'drivers' => 2,
        ]);
});

/*
 * Existing free stores get limits added later from their plan's row, which
 * deploys add with a migration (they don't run seeders). A value someone
 * already set is kept.
 */
it('gives the free plan a limit added later on deploy, keeping a value already set', function (string $migrationFile, string $limitKey, int $limitValue): void {
    $migration = require database_path('migrations/landlord/'.$migrationFile);

    $migration->up();
    expect(Plan::query()->count())->toBe(0);

    $plan = Plan::factory()->create(['code' => config()->string('platform.store_registration.plan')]);
    $migration->up();
    expect($plan->limits()->where('limit_key', $limitKey)->value('limit_value'))->toBe($limitValue);

    $plan->limits()->where('limit_key', $limitKey)->update(['limit_value' => 4]);
    $migration->up();
    expect($plan->limits()->where('limit_key', $limitKey)->value('limit_value'))->toBe(4);
})->with([
    'presentment currencies' => ['2026_10_13_130100_add_presentment_currencies_limit_to_free_plan.php', 'presentment_currencies', 1],
    'drivers' => ['2026_10_28_100000_add_drivers_limit_to_free_plan.php', 'drivers', 2],
]);
