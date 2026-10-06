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
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('status', 24)->index();
            $table->string('hosting_region', 32);
            $table->foreignId('database_server_id')->nullable()->constrained('database_servers')->restrictOnDelete();
            // The owner's contact for running the platform (billing, store notices); the account itself lives in the store's database.
            $table->string('owner_name', 120);
            $table->string('owner_email', 254)->index();
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->string('timezone', 64);
            $table->string('locale', 12);
            $table->timestampTz('provisioned_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('database_server_id');
            $table->dropColumn([
                'public_id', 'name', 'status', 'hosting_region', 'owner_name', 'owner_email',
                'country_code', 'currency_code', 'timezone', 'locale', 'provisioned_at',
            ]);
        });
    }
};
