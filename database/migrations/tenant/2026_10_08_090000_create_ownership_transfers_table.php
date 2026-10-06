<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Section 10: an ownership transfer waits for the new owner to accept it.
 * The partial unique index lets a store have only one pending transfer,
 * even when two requests start one at the same moment.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ownership_transfers', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('from_staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $table->foreignId('to_staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $table->string('status', 16);
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("create unique index ownership_transfers_one_pending on ownership_transfers (status) where status = 'pending'");
        DB::statement('alter table ownership_transfers add constraint ownership_transfers_to_someone_else check (from_staff_member_id <> to_staff_member_id)');

        // The roles the current owner keeps once the transfer is accepted; may be none.
        Schema::create('ownership_transfer_kept_roles', static function (Blueprint $table): void {
            $table->foreignId('ownership_transfer_id')->constrained('ownership_transfers')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['ownership_transfer_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ownership_transfer_kept_roles');
        Schema::dropIfExists('ownership_transfers');
    }
};
