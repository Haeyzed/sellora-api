<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The platform's own activity log: what platform admins and platform
 * processes did (registering stores, publishing terms). Each store keeps its
 * own activity log in its database; this one never holds store activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            // String IDs: a subject can be a store, whose ID is a UUID, or a record with a numeric ID.
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id', 36)->nullable();
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
