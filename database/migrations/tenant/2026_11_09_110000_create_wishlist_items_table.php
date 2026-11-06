<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Wishlists (Step 19b, section 10): what a signed-in customer saved to buy
 * later. A product, and optionally the variant they chose (a shopper may
 * save before picking a size). No quantity, no price and no stock is held.
 * Each product, or product and variant, is saved once per customer.
 * Personal data: in the customer's data export, deleted by erasure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', static function (Blueprint $table): void {
            $table->comment('What each signed-in customer saved to buy later: a product, and optionally its variant. Personal data.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->foreignId('customer_id')->comment('The customer who saved it.')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->comment('The product saved; shown as unavailable while customers can\'t buy it.')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->comment('The variant chosen, of that product; null when the customer saved the product before choosing one.')->constrained('product_variants')->cascadeOnDelete();
            $table->timestampTz('created_at')->comment('When the customer saved it.');

            $table->index(['customer_id', 'id']);
        });

        DB::statement('alter table wishlist_items add constraint wishlist_items_saved_once unique nulls not distinct (customer_id, product_id, product_variant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
