<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What is actually sold: every product has at least one variant. Prices are
 * whole minor units in the store's base currency (section 3.3), and a
 * compare-at price must be higher than the price. Weights are grams and
 * dimensions millimetres (section 13); anything that ships has a weight.
 * A SKU is unique only among variants outside the trash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->bigInteger('price_amount');
            $table->bigInteger('compare_at_price_amount')->nullable();
            $table->char('currency', 3);
            $table->boolean('requires_shipping')->default(true);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['product_id', 'position']);
        });

        DB::statement('create unique index product_variants_sku_unique on product_variants (sku) where sku is not null and deleted_at is null');
        DB::statement('alter table product_variants add constraint product_variants_price_not_negative check (price_amount >= 0)');
        DB::statement('alter table product_variants add constraint product_variants_compare_at_above_price check (compare_at_price_amount is null or compare_at_price_amount > price_amount)');
        DB::statement("alter table product_variants add constraint product_variants_currency_code check (currency ~ '^[A-Z]{3}$')");
        DB::statement('alter table product_variants add constraint product_variants_shipped_have_weight check (not requires_shipping or weight_grams is not null)');
        DB::statement('alter table product_variants add constraint product_variants_dimensions_complete check (
            (length_mm is null and width_mm is null and height_mm is null)
            or (length_mm is not null and width_mm is not null and height_mm is not null and length_mm > 0 and width_mm > 0 and height_mm > 0)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
