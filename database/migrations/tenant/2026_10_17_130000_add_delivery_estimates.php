<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Delivery estimates (Step 13): a method may say how many business days it
 * takes, at least and at most (both or neither, up to a year), and the
 * store says which days of the week it works (ISO numbers, 1 for Monday to
 * 7 for Sunday; Monday to Friday until changed). Dates are worked out in the
 * store's timezone. No holidays yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', static function (Blueprint $table): void {
            $table->smallInteger('min_days')->nullable()->after('backup_amount');
            $table->smallInteger('max_days')->nullable()->after('min_days');
        });
        DB::statement(<<<'SQL'
            alter table shipping_methods add constraint shipping_methods_days check (
                (min_days is null) = (max_days is null)
                and (min_days is null or (min_days >= 0 and max_days >= min_days and max_days <= 365))
            )
            SQL);

        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->jsonb('working_days')->default(DB::raw("'[1, 2, 3, 4, 5]'::jsonb"));
        });
        DB::statement(<<<'SQL'
            alter table store_settings add constraint store_settings_working_days check (
                jsonb_typeof(working_days) = 'array' and jsonb_array_length(working_days) between 1 and 7
            )
            SQL);
    }

    public function down(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->dropColumn('working_days');
        });
        Schema::table('shipping_methods', static function (Blueprint $table): void {
            $table->dropColumn(['min_days', 'max_days']);
        });
    }
};
