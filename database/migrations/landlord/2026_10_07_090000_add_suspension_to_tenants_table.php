<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->timestampTz('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
