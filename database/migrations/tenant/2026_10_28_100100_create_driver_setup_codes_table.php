<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * One-time codes staff issue so a driver can choose their own PIN (Step 17).
 * Only a keyed hash is stored; the code is shown once. A driver has at most
 * one live code: issuing another voids the last, and so do 5 wrong tries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_setup_codes', static function (Blueprint $table): void {
            $table->comment('One-time codes staff issue so a driver can choose their own PIN. Staff never see or set the PIN itself.');
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->string('code_hash', 64)->comment('Keyed hash of the 8-digit code; the code itself is shown once and never stored.');
            $table->smallInteger('failed_attempts')->default(0)->comment('Wrong codes tried; the code is voided when it reaches the limit in config/delivery.php.');
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable()->comment('When the driver chose their PIN with it.');
            $table->timestampTz('voided_at')->nullable()->comment('When a newer code, deactivation, a phone change or too many wrong tries made it stop working.');
            $table->string('issued_by_type', 64)->comment('Account type of the staff member who issued it.');
            $table->string('issued_by_id', 64)->comment('Public ID of the staff member who issued it.');
            $table->timestampsTz();
            $table->index('expires_at');
        });

        DB::statement(<<<'SQL'
            alter table driver_setup_codes
                add constraint driver_setup_codes_failed_attempts_not_negative check (failed_attempts >= 0),
                add constraint driver_setup_codes_used_or_voided check (used_at is null or voided_at is null)
            SQL);
        DB::statement('create unique index driver_setup_codes_one_live_per_driver on driver_setup_codes (driver_id) where used_at is null and voided_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_setup_codes');
    }
};
