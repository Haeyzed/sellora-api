<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What a unit of each variant costs the store, moved here from the catalog
 * (section 3.3): an internal figure in the store's base currency with six
 * decimal places, for bulk costs such as 0.000125 per unit. Never shown to
 * customers. A receipt can record the cost the stock arrived at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', static function (Blueprint $table): void {
            $table->decimal('unit_cost', 20, 6)->nullable();
        });
        DB::statement('alter table inventory_items add constraint inventory_items_unit_cost_not_negative check (unit_cost is null or unit_cost >= 0)');

        Schema::table('stock_movements', static function (Blueprint $table): void {
            $table->decimal('unit_cost', 20, 6)->nullable();
        });
        DB::statement("alter table stock_movements add constraint stock_movements_unit_cost_on_receipts check (unit_cost is null or (unit_cost >= 0 and type = 'received'))");
    }

    public function down(): void
    {
        Schema::table('stock_movements', static function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
        Schema::table('inventory_items', static function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
