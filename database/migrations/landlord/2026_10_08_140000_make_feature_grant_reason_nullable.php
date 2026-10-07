<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Section 9.1: purging a store clears free-text reasons, which may hold
 * personal data, from its feature grants as well. A grant still needs a
 * reason when it is made (validated on the request); only a purge empties it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feature_grants', static function (Blueprint $table): void {
            $table->string('reason', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Fails while purged stores' grants exist: their reasons are gone for good.
        Schema::table('feature_grants', static function (Blueprint $table): void {
            $table->string('reason', 500)->nullable(false)->change();
        });
    }
};
