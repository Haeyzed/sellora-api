<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Landlord\Plans\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Creates the "free" plan every new store starts on until merchant billing is decided. Safe to run again: it resets the plan to these limits.
 *
 * No paid modules or integrations. A limit left out allows none, so a free
 * store has no extra locations or custom domains.
 */
final class FreePlanSeeder extends Seeder
{
    /**
     * The approved starter limits.
     *
     * @var array<string, int>
     */
    private const array LIMITS = [
        'products' => 100,
        // Beyond the owner, who never counts.
        'staff_accounts' => 2,
        'storage_in_megabytes' => 1024,
    ];

    public function run(): void
    {
        $plan = Plan::query()->updateOrCreate(
            ['code' => config()->string('platform.store_registration.plan')],
            ['name' => 'Free', 'is_active' => true, 'is_public' => true, 'sort_order' => 0],
        );

        $plan->features()->delete();
        $plan->limits()->whereNotIn('limit_key', array_keys(self::LIMITS))->delete();

        foreach (self::LIMITS as $limitKey => $limitValue) {
            $plan->limits()->updateOrCreate(['limit_key' => $limitKey], ['limit_value' => $limitValue]);
        }
    }
}
