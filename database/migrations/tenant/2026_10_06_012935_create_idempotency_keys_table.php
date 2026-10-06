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
        Schema::create('idempotency_keys', static function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 191);
            $table->string('route', 191);
            $table->string('key', 128);
            $table->char('request_hash', 64);
            $table->string('status', 16);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_content_type', 128)->nullable();
            $table->longText('response_body')->nullable();
            $table->timestampTz('locked_until');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');

            $table->unique(['scope', 'route', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
