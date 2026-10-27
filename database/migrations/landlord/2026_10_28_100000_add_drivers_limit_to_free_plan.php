<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The free plan allows two drivers (Step 17); a plan without the limit
 * allows none. Stores read their plan's limits live, so every existing free
 * store gets it from this row; deploys run migrations, not seeders. A value
 * someone already set is kept. Other plans stay at none until a platform
 * admin sets them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $planId = DB::table('plans')->where('code', config()->string('platform.store_registration.plan'))->value('id');

        if ($planId === null) {
            return;
        }

        DB::table('plan_limits')->insertOrIgnore(['plan_id' => $planId, 'limit_key' => 'drivers', 'limit_value' => 2]);
    }

    public function down(): void
    {
        // Removing the limit would leave free stores with no drivers; it stays.
    }
};
