<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Sections 6 and 10: the store commits an accepted transfer first, and a
 * queued job sends the terms of service acceptance to the platform
 * afterwards. So the store keeps what was accepted, and from where, until
 * the platform has it; an accepted transfer without it is impossible.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ownership_transfers', static function (Blueprint $table): void {
            $table->string('accepted_terms_of_service_id', 26)->nullable();
            $table->ipAddress('acceptance_ip_address')->nullable();
            $table->string('acceptance_user_agent', 512)->nullable();
        });

        DB::statement("alter table ownership_transfers add constraint ownership_transfers_acceptance_is_complete check (status <> 'accepted' or (accepted_at is not null and accepted_terms_of_service_id is not null))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('alter table ownership_transfers drop constraint if exists ownership_transfers_acceptance_is_complete');

        Schema::table('ownership_transfers', static function (Blueprint $table): void {
            $table->dropColumn(['accepted_terms_of_service_id', 'acceptance_ip_address', 'acceptance_user_agent']);
        });
    }
};
