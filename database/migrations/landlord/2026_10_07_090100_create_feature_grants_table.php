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
        Schema::create('feature_grants', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('tenant_id');
            $table->string('feature_key', 64);
            $table->string('reason', 500);
            $table->foreignId('granted_by_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['tenant_id', 'feature_key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_grants');
    }
};
