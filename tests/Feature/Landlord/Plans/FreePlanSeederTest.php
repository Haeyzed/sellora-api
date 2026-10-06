<?php

declare(strict_types=1);

use App\Landlord\Plans\Models\Plan;
use App\Landlord\Tenancy\Services\StoreRegistrationRequirements;
use Database\Seeders\Landlord\FreePlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

/*
 * Section 9.1: new stores start on the "free" plan, with the approved
 * starter limits and no paid modules.
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
        ]);
});
