<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The free plan allows one presentment currency besides the base currency
 * (section 3.3). Stores read their plan's limits live (cached for at most
 * ten minutes), so every existing free store gets it from this row; deploys
 * run migrations, not seeders. A value someone already set is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $planId = DB::table('plans')->where('code', config()->string('platform.store_registration.plan'))->value('id');

        if ($planId === null) {
            return;
        }

        DB::table('plan_limits')->insertOrIgnore(['plan_id' => $planId, 'limit_key' => 'presentment_currencies', 'limit_value' => 1]);
    }

    public function down(): void
    {
        // Removing the limit would leave free stores with none; it stays.
    }
};
