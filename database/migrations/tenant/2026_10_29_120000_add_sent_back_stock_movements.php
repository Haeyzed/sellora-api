<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Units of a rejected return going back to the buyer (Step 18): inspection
 * had already restocked or written them off, so rejecting it records new
 * movements, never edits: a written-off unit's write-off is reversed
 * ("write_off_reversed"), and every unit leaves again ("sent_back"). On hand
 * ends where it was before the inspection.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted', 'reserved', 'released', 'committed', 'expired', 'returned', 'restocked', 'returned_damaged', 'written_off', 'write_off_reversed', 'sent_back'))");
    }

    public function down(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted', 'reserved', 'released', 'committed', 'expired', 'returned', 'restocked', 'returned_damaged', 'written_off'))");
    }
};
