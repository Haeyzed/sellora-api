<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The store's brands. Names and descriptions are JSON objects keyed by
 * language. A slug is unique only among brands outside the trash, so a
 * trashed brand never blocks a new one (section 13); restoring re-checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->jsonb('name');
            $table->string('slug', 190);
            $table->jsonb('description')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('create unique index brands_slug_unique on brands (slug) where deleted_at is null');
        DB::statement("alter table brands add constraint brands_slug_format check (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("alter table brands add constraint brands_name_translated check (jsonb_typeof(name) = 'object' and name <> '{}'::jsonb)");
        DB::statement("alter table brands add constraint brands_description_translated check (description is null or jsonb_typeof(description) = 'object')");
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
