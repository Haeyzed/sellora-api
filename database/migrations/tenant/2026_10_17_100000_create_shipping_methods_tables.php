<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Shipping methods and their rates (Step 13).
 *
 * A method ships (a carrier the merchant books, or the store's own delivery
 * fleet) within one zone, or is a pickup at one of the store's locations,
 * offered to shoppers in that location's country. Its price comes from rate
 * brackets: one flat bracket, brackets of total weight in grams, or brackets
 * of order value in the base currency's minor units. A bracket covers
 * [from, to); no "to" means and up. One method's brackets can't overlap: an
 * exclusion constraint on the range enforces it (btree_gist lets the method
 * id take part). Gaps are allowed and mean "not offered".
 *
 * Amounts are whole minor units of the store's base currency (currency), with
 * optional exact amounts per offered currency, used instead of converting,
 * like fixed prices. Methods are archived, never deleted, so orders keep
 * what they chose; an archived method may outlive its zone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists btree_gist');

        Schema::create('shipping_methods', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shipping_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('fulfilment', 20);
            $table->string('pricing', 20);
            $table->char('currency', 3);
            $table->bigInteger('free_over_amount')->nullable();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->char('pickup_country_code', 2)->nullable();
            $table->string('pickup_state_code', 100)->nullable();
            $table->string('pickup_address_line1', 255)->nullable();
            $table->string('pickup_city', 100)->nullable();
            $table->string('pickup_postal_code', 20)->nullable();
            $table->jsonb('pickup_instructions')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->index(['shipping_zone_id', 'position']);
        });
        DB::statement(<<<'SQL'
            alter table shipping_methods
                add constraint shipping_methods_fulfilment check (fulfilment in ('ship', 'own_delivery', 'pickup')),
                add constraint shipping_methods_pricing check (pricing in ('flat', 'weight', 'order_value')),
                add constraint shipping_methods_name_translated check (jsonb_typeof(name) = 'object' and name <> '{}'::jsonb),
                add constraint shipping_methods_currency check (currency ~ '^[A-Z]{3}$'),
                add constraint shipping_methods_free_over_positive check (free_over_amount is null or free_over_amount > 0),
                add constraint shipping_methods_pickup check (
                    (fulfilment = 'pickup'
                        and pricing = 'flat'
                        and shipping_zone_id is null
                        and free_over_amount is null
                        and stock_location_id is not null
                        and pickup_country_code ~ '^[A-Z]{2}$'
                        and pickup_address_line1 is not null
                        and pickup_city is not null)
                    or (fulfilment <> 'pickup'
                        and (shipping_zone_id is not null or archived_at is not null)
                        and stock_location_id is null
                        and pickup_country_code is null
                        and pickup_state_code is null
                        and pickup_address_line1 is null
                        and pickup_city is null
                        and pickup_postal_code is null
                        and pickup_instructions is null)
                )
            SQL);

        Schema::create('shipping_method_amounts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->bigInteger('free_over_amount');
            $table->unique(['shipping_method_id', 'currency_code']);
        });
        DB::statement(<<<'SQL'
            alter table shipping_method_amounts
                add constraint shipping_method_amounts_currency_code check (currency_code ~ '^[A-Z]{3}$'),
                add constraint shipping_method_amounts_free_over_positive check (free_over_amount > 0)
            SQL);

        Schema::create('shipping_rates', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->bigInteger('range_from');
            $table->bigInteger('range_to')->nullable();
            $table->bigInteger('amount');
        });
        DB::statement(<<<'SQL'
            alter table shipping_rates
                add constraint shipping_rates_range check (range_from >= 0 and (range_to is null or range_to > range_from)),
                add constraint shipping_rates_amount check (amount >= 0),
                add constraint shipping_rates_no_overlap exclude using gist (shipping_method_id with =, int8range(range_from, range_to) with &&)
            SQL);

        Schema::create('shipping_rate_amounts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_rate_id')->constrained('shipping_rates')->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->bigInteger('price_amount');
            $table->unique(['shipping_rate_id', 'currency_code']);
        });
        DB::statement(<<<'SQL'
            alter table shipping_rate_amounts
                add constraint shipping_rate_amounts_currency_code check (currency_code ~ '^[A-Z]{3}$'),
                add constraint shipping_rate_amounts_price check (price_amount >= 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rate_amounts');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_method_amounts');
        Schema::dropIfExists('shipping_methods');
    }
};
