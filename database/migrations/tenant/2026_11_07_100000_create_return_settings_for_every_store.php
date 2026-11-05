<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Every store has its return settings row from now on (CLAUDE.md section 10):
 * a store set up before this migration, whose staff never changed its return
 * settings, gets the row from the defaults in config/returns.php, the values
 * it was already using. A store that has the row keeps it as it is. New
 * stores get it when they are set up; reads never fill it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = CarbonImmutable::now();

        DB::table('return_settings')->insertOrIgnore([
            'id' => 1,
            'window_days' => config()->integer('returns.window_days'),
            'customer_requests' => config()->boolean('returns.customer_requests'),
            'photos_required' => config()->boolean('returns.photos_required'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::statement("comment on table return_settings is 'How the store takes returns. One row, created with the store from the defaults in config/returns.php and never missing.'");
    }

    public function down(): void
    {
        // The row stays: before this migration it was simply read with the same defaults.
        DB::statement("comment on table return_settings is 'How the store takes returns. One row, absent until staff first change it; the defaults are in config/returns.php.'");
    }
};
