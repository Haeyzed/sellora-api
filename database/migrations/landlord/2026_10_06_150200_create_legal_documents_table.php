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
        Schema::create('legal_documents', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('type', 32);
            $table->string('version', 32);
            $table->string('title');
            $table->text('body');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampsTz();

            $table->unique(['type', 'version']);
            $table->index(['type', 'effective_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
