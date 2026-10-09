<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Product search. Every product keeps one lower-case search text (its names
 * in every language, its variants' SKUs and barcodes, its brand's names),
 * and a trigram index (pg_trgm) answers "contains" searches on it quickly
 * and case-insensitively. pg_trgm is a trusted extension from PostgreSQL 13,
 * so the store database's owner can create it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists pg_trgm');

        Schema::table('products', static function (Blueprint $table): void {
            $table->text('search_text')->default('');
        });

        // Products made before search existed.
        DB::statement(<<<'SQL'
            update products p set search_text = lower(concat_ws(' ',
                (select string_agg(name.value, ' ') from jsonb_each_text(p.name) as name),
                (select string_agg(concat_ws(' ', v.sku, v.barcode), ' ') from product_variants v where v.product_id = p.id and v.deleted_at is null),
                (select string_agg(brand_name.value, ' ') from brands b cross join jsonb_each_text(b.name) as brand_name where b.id = p.brand_id)
            ))
            SQL);

        DB::statement('create index products_search_text_trigram on products using gin (search_text gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('drop index if exists products_search_text_trigram');

        Schema::table('products', static function (Blueprint $table): void {
            $table->dropColumn('search_text');
        });
    }
};
