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
        Schema::create('tenant_limit_overrides', static function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('limit_key', 64);
            $table->unsignedBigInteger('limit_value')->nullable()->comment('null means unlimited');
            $table->timestampTz('expires_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'limit_key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_limit_overrides');
    }
};
