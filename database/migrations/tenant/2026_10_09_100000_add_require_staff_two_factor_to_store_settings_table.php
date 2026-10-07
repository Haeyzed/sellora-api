<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Section 10: two-factor authentication is a store setting for staff, which
 * only the owner can turn on. Off until they do.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->boolean('require_staff_two_factor')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->dropColumn('require_staff_two_factor');
        });
    }
};
