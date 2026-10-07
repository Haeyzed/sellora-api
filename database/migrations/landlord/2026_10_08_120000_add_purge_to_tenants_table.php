<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Section 9.1: a purged store keeps its row, without the owner's personal
 * data. The check constraint allows missing owner details only on a purged
 * store, so a bug can't lose a live store's owner contact. A purged store's
 * subdomain is held for a while, so nobody can take over its old links.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->timestampTz('purged_at')->nullable();
            $table->string('owner_name', 120)->nullable()->change();
            $table->string('owner_email', 254)->nullable()->change();
        });

        DB::statement("alter table tenants add constraint tenants_owner_details_until_purged check (status = 'purged' or (owner_name is not null and owner_email is not null))");

        Schema::create('released_subdomains', static function (Blueprint $table): void {
            $table->id();
            $table->string('subdomain', 63)->unique();
            $table->timestampTz('released_at');
            $table->timestampTz('held_until')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('released_subdomains');
        DB::statement('alter table tenants drop constraint if exists tenants_owner_details_until_purged');

        // Fails while purged stores exist: their owner details are gone for good.
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->dropColumn('purged_at');
            $table->string('owner_name', 120)->nullable(false)->change();
            $table->string('owner_email', 254)->nullable(false)->change();
        });
    }
};
