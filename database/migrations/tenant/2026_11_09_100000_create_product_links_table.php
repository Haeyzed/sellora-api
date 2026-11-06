<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Product links (Step 19b, section 13.1): the products a merchant picks to
 * show beside one product, as related products, upsells or cross-sells, in
 * their order. A product never links to itself, and links to the same
 * product once per kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_links', static function (Blueprint $table): void {
            $table->comment('The products a merchant picks to show beside a product: related products, upsells and cross-sells, in order.');
            $table->id();
            $table->foreignId('product_id')->comment('The product the links are shown beside.')->constrained('products')->cascadeOnDelete();
            $table->string('type', 16)->comment('What kind of link it is: App\Tenant\Catalog\Enums\ProductLinkType.');
            $table->foreignId('linked_product_id')->comment('The product shown; shoppers see it only while they can buy it.')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->comment('Its place among the product\'s links of this kind, from 0.');
            $table->timestampsTz();

            $table->unique(['product_id', 'type', 'linked_product_id']);
            $table->unique(['product_id', 'type', 'position']);
            $table->index('linked_product_id');
        });

        DB::statement(<<<'SQL'
            alter table product_links
                add constraint product_links_never_to_itself check (product_id <> linked_product_id),
                add constraint product_links_type_check check (type in ('related', 'upsell', 'cross_sell'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_links');
    }
};
