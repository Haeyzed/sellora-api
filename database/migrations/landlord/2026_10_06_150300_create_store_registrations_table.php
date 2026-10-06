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
        Schema::create('store_registrations', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('store_name', 120);
            $table->string('subdomain', 63)->index();
            $table->string('owner_name', 120);
            $table->string('email', 254)->index();
            // The owner's password hash, kept only until their account exists in the new store.
            $table->string('password_hash')->nullable();
            $table->char('country_code', 2);
            $table->string('timezone', 64);
            $table->string('hosting_region', 32);
            $table->char('verification_code_hash', 64);
            $table->unsignedSmallInteger('verification_attempts')->default(0);
            $table->timestampTz('verification_code_sent_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('verified_at')->nullable();
            $table->string('tenant_id')->nullable()->unique();
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::create('legal_acceptances', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->restrictOnDelete();
            // Set until the registration becomes a store. Acceptances of a sign-up that was never completed go with it.
            $table->foreignId('store_registration_id')->nullable()->constrained('store_registrations')->cascadeOnDelete();
            // The acceptance outlives the store row: it is evidence of the contract.
            $table->string('tenant_id')->nullable()->index();
            $table->string('accepted_by_name', 120);
            $table->string('accepted_by_email', 254);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('accepted_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('store_registrations');
    }
};
