<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A variant can exist before it has a price, such as a new size added to a
 * product, but can't be bought until it has one (section 3.3). An unpriced
 * variant has no currency and no compare-at price. Every check spells out
 * its NULL cases, because a check whose condition is NULL passes
 * (section 13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->bigInteger('price_amount')->nullable()->change();
            $table->char('currency', 3)->nullable()->change();
        });

        DB::statement('alter table product_variants drop constraint product_variants_price_not_negative');
        DB::statement('alter table product_variants drop constraint product_variants_compare_at_above_price');
        DB::statement('alter table product_variants drop constraint product_variants_currency_code');

        DB::statement('alter table product_variants add constraint product_variants_price_not_negative check (price_amount is null or price_amount >= 0)');
        DB::statement('alter table product_variants add constraint product_variants_compare_at_above_price check (
            compare_at_price_amount is null or (price_amount is not null and compare_at_price_amount > price_amount)
        )');
        DB::statement("alter table product_variants add constraint product_variants_currency_code check (currency is null or currency ~ '^[A-Z]{3}$')");
        DB::statement('alter table product_variants add constraint product_variants_priced_have_currency check (
            (price_amount is null and currency is null) or (price_amount is not null and currency is not null)
        )');
    }

    public function down(): void
    {
        DB::statement('alter table product_variants drop constraint product_variants_priced_have_currency');
        DB::statement('alter table product_variants drop constraint product_variants_currency_code');
        DB::statement('alter table product_variants drop constraint product_variants_compare_at_above_price');
        DB::statement('alter table product_variants drop constraint product_variants_price_not_negative');

        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->bigInteger('price_amount')->nullable(false)->change();
            $table->char('currency', 3)->nullable(false)->change();
        });

        DB::statement('alter table product_variants add constraint product_variants_price_not_negative check (price_amount >= 0)');
        DB::statement('alter table product_variants add constraint product_variants_compare_at_above_price check (compare_at_price_amount is null or compare_at_price_amount > price_amount)');
        DB::statement("alter table product_variants add constraint product_variants_currency_code check (currency ~ '^[A-Z]{3}$')");
    }
};
