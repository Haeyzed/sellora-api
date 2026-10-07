<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Section 6: a store's terms of service acceptance reaches the platform
 * through a job that may run more than once. The reference says what the
 * acceptance was part of (for example an ownership transfer), and the unique
 * index records it once per store however often the job runs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('legal_acceptances', static function (Blueprint $table): void {
            $table->string('reference', 64)->nullable();
            $table->unique(['tenant_id', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_acceptances', static function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
