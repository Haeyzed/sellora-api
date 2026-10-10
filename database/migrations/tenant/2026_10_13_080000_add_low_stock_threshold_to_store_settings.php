<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The stock level at or below which a variant counts as running low, for
 * every variant that doesn't set its own (section 13). 5 until changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->unsignedInteger('low_stock_threshold')->default(5);
        });

        DB::statement('alter table store_settings add constraint store_settings_low_stock_threshold_not_negative check (low_stock_threshold >= 0)');
    }

    public function down(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
