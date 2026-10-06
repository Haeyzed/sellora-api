<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Section 8: unlimited is stated on purpose, never read from an empty value.
 * The check constraint makes the database refuse a row whose value is
 * missing without the unlimited flag, so a bug can't store one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_limit_overrides', static function (Blueprint $table): void {
            $table->boolean('is_unlimited')->default(false);
        });

        // Rows written before the flag existed used null for unlimited; keep their meaning.
        DB::table('tenant_limit_overrides')->whereNull('limit_value')->update(['is_unlimited' => true]);

        Schema::table('tenant_limit_overrides', static function (Blueprint $table): void {
            $table->unsignedBigInteger('limit_value')->nullable()->comment('Null only when is_unlimited is true')->change();
        });

        DB::statement('alter table tenant_limit_overrides add constraint tenant_limit_overrides_unlimited_is_explicit check ((is_unlimited and limit_value is null) or (not is_unlimited and limit_value is not null))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('alter table tenant_limit_overrides drop constraint if exists tenant_limit_overrides_unlimited_is_explicit');

        Schema::table('tenant_limit_overrides', static function (Blueprint $table): void {
            $table->dropColumn('is_unlimited');
        });
    }
};
