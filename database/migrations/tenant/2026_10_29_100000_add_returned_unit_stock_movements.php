<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Units a buyer sent back (Step 18): back on the shelf ("restocked"), or,
 * when they came back damaged, recorded as they arrive ("returned_damaged")
 * and written off at once ("written_off"), so the ledger shows every unit
 * that came back even when none of it can be sold again.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted', 'reserved', 'released', 'committed', 'expired', 'returned', 'restocked', 'returned_damaged', 'written_off'))");
    }

    public function down(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted', 'reserved', 'released', 'committed', 'expired', 'returned'))");
    }
};
