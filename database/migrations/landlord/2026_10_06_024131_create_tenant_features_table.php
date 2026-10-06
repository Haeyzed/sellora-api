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
        Schema::create('tenant_features', static function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature_key', 64);
            $table->timestampTz('removed_from_plan_at')->nullable();
            $table->timestampTz('disabled_by_merchant_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'feature_key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_features');
    }
};
