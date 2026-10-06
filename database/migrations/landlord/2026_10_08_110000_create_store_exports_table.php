<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Section 9.1: full exports of a store's data. Who asked is kept as an
 * account type and public ID, because the owner lives in the store's own
 * database and can't be a foreign key here. The partial unique index allows
 * one export in progress per store, even under racing requests.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_exports', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_id');
            $table->string('requested_by_type', 32);
            $table->string('requested_by_id', 64);
            $table->string('status', 16);
            // The private disk in the store's hosting region, and the file's path on it.
            $table->string('disk', 64);
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'requested_by_type', 'requested_by_id']);
        });

        DB::statement("create unique index store_exports_one_in_progress on store_exports (tenant_id) where status in ('queued', 'building')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_exports');
    }
};
