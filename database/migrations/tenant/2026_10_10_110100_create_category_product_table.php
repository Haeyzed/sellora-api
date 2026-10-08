<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Which categories each product is in. A product's primary category must be
 * one of them: a foreign key from products to this table enforces it,
 * checked when the transaction commits, so the membership and the primary
 * category can be changed in either order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', static function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });

        DB::statement('alter table products add constraint products_primary_category_assigned
            foreign key (primary_category_id, id) references category_product (category_id, product_id)
            deferrable initially deferred');
    }

    public function down(): void
    {
        DB::statement('alter table products drop constraint products_primary_category_assigned');
        Schema::dropIfExists('category_product');
    }
};
