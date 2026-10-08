<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The store's products. Names and descriptions are JSON objects keyed by
 * language. Brands and categories are only ever trashed, never deleted, so
 * a product never loses them from under it. A slug is unique only among
 * products outside the trash (section 13). Prices live on the variants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('status', 16)->default('draft');
            $table->jsonb('name');
            $table->string('slug', 190);
            $table->jsonb('description')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->unsignedBigInteger('primary_category_id')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['status', 'id']);
            $table->index('brand_id');
        });

        DB::statement('create unique index products_slug_unique on products (slug) where deleted_at is null');
        DB::statement("alter table products add constraint products_slug_format check (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("alter table products add constraint products_name_translated check (jsonb_typeof(name) = 'object' and name <> '{}'::jsonb)");
        DB::statement("alter table products add constraint products_description_translated check (description is null or jsonb_typeof(description) = 'object')");
        DB::statement("alter table products add constraint products_known_status check (status in ('draft', 'active', 'archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
