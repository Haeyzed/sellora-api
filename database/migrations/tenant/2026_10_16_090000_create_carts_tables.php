<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Carts (Step 12): what a shopper means to buy, before checkout. A cart is
 * a signed-in customer's (at most one each) or a guest's, found by the hash
 * of the token it was given once; never both. It holds variants and
 * quantities only: prices, tax and discounts are worked out on every read,
 * and stock is never held for it. Its version goes up with every change, so
 * an edit made from an out-of-date view is refused.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('carts', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->nullable()->unique()->constrained('customers')->cascadeOnDelete();
            $table->char('token_hash', 64)->nullable()->unique();
            // The currency the shopper chose; null for the store's own. Priced in the store's own while it isn't offered.
            $table->char('currency', 3)->nullable();
            // Where the shopper said they are, for the tax estimate; null for the store's own country.
            $table->char('destination_country_code', 2)->nullable();
            // The one promotion code entered, as the store spells it; kept while it doesn't apply yet, with the reason shown.
            $table->string('promotion_code', 32)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('last_activity_at');
            $table->timestampsTz();

            $table->index('last_activity_at');
        });

        DB::statement(<<<'SQL'
            alter table carts
                add constraint carts_customer_or_guest check ((customer_id is null) <> (token_hash is null)),
                add constraint carts_token_hash_format check (token_hash is null or token_hash ~ '^[0-9a-f]{64}$'),
                add constraint carts_version_positive check (version >= 1),
                add constraint carts_currency_code check (currency is null or currency ~ '^[A-Z]{3}$'),
                add constraint carts_destination_country_code check (destination_country_code is null or destination_country_code ~ '^[A-Z]{2}$')
            SQL);

        Schema::create('cart_lines', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            // Moving a variant to the trash keeps the line, shown as unavailable; only deleting it for good removes it.
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            // The unit price the shopper last saw, to tell them when it changes: a fixed price in its own currency, otherwise
            // the base price in the base currency, so exchange rates moving never counts as a change.
            $table->bigInteger('seen_price_amount');
            $table->char('seen_price_currency', 3);
            $table->string('seen_price_source', 10);
            $table->timestampsTz();

            $table->unique(['cart_id', 'product_variant_id']);
            $table->index('product_variant_id');
        });

        DB::statement(<<<'SQL'
            alter table cart_lines
                add constraint cart_lines_quantity_positive check (quantity >= 1),
                add constraint cart_lines_seen_price_not_negative check (seen_price_amount >= 0),
                add constraint cart_lines_seen_price_currency check (seen_price_currency ~ '^[A-Z]{3}$'),
                add constraint cart_lines_seen_price_source check (seen_price_source in ('fixed', 'converted'))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_lines');
        Schema::dropIfExists('carts');
    }
};
