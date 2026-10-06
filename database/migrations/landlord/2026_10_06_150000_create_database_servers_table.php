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
        Schema::create('database_servers', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 64)->unique();
            $table->string('region', 32)->index();
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('username', 128);
            // Encrypted with the app key; never returned by the API.
            $table->text('password');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('tenant_count')->default(0);
            $table->boolean('accepting_new_tenants')->default(true);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_servers');
    }
};
