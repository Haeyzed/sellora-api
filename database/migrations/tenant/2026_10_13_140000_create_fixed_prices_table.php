<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Fixed prices of a variant in the store's other currencies (section 3.3):
 * optional, in whole minor units, used as they are instead of converting the
 * base price. One per variant and currency; a compare-at price, when set, is
 * higher than the price, as on the variant itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_prices', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->bigInteger('price_amount');
            $table->bigInteger('compare_at_price_amount')->nullable();
            $table->timestampsTz();
            $table->unique(['product_variant_id', 'currency_code']);
            $table->index('currency_code');
        });
        DB::statement("alter table fixed_prices add constraint fixed_prices_currency_code check (currency_code ~ '^[A-Z]{3}$')");
        DB::statement('alter table fixed_prices add constraint fixed_prices_price_not_negative check (price_amount >= 0)');
        DB::statement('alter table fixed_prices add constraint fixed_prices_compare_at_above_price check (compare_at_price_amount is null or compare_at_price_amount > price_amount)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_prices');
    }
};
