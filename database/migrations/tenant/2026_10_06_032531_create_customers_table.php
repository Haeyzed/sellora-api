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
        Schema::create('customers', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_signed_in_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('customer_password_reset_tokens', static function (Blueprint $table): void {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::dropIfExists('customers');
    }
};
