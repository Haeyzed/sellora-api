<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The store's category tree. Names and descriptions are JSON objects keyed by
 * language. Categories are only ever trashed, never deleted, so a parent can
 * never disappear from under its subcategories. A slug is unique only among
 * categories outside the trash (section 13); restoring re-checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->jsonb('name');
            $table->string('slug', 190);
            $table->jsonb('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['parent_id', 'position']);
        });

        DB::statement('create unique index categories_slug_unique on categories (slug) where deleted_at is null');
        DB::statement("alter table categories add constraint categories_slug_format check (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("alter table categories add constraint categories_name_translated check (jsonb_typeof(name) = 'object' and name <> '{}'::jsonb)");
        DB::statement("alter table categories add constraint categories_description_translated check (description is null or jsonb_typeof(description) = 'object')");
        DB::statement('alter table categories add constraint categories_not_own_parent check (parent_id is null or parent_id <> id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
