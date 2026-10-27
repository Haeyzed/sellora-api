<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Returned stock (Step 17): stock that left the shelf for an order and came
 * back undelivered, such as a delivery the driver brought back. It is on hand
 * again and held for the order once more.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted', 'reserved', 'released', 'committed', 'expired', 'returned'))");
    }

    public function down(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted', 'reserved', 'released', 'committed', 'expired'))");
    }
};
