<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Section 9.1: a closed store remembers when and why it closed, who closed
 * it, the status to return to if it is restored, and when it will be
 * purged. The check constraint makes a closed store without those
 * impossible, so a restore always knows where to return to.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->timestampTz('closed_at')->nullable();
            $table->string('closure_reason', 500)->nullable();
            // Who closed it: an account type ("platform_admin", "staff_member") and that account's public ID.
            $table->string('closed_by_type', 32)->nullable();
            $table->string('closed_by_id', 64)->nullable();
            $table->string('status_before_closing', 32)->nullable();
            $table->timestampTz('purge_after')->nullable()->index();
            $table->timestampTz('purge_reminder_sent_at')->nullable();
        });

        DB::statement("alter table tenants add constraint tenants_closure_is_complete check (status <> 'closed' or (closed_at is not null and purge_after is not null and status_before_closing is not null))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('alter table tenants drop constraint if exists tenants_closure_is_complete');

        Schema::table('tenants', static function (Blueprint $table): void {
            $table->dropColumn(['closed_at', 'closure_reason', 'closed_by_type', 'closed_by_id', 'status_before_closing', 'purge_after', 'purge_reminder_sent_at']);
        });
    }
};
