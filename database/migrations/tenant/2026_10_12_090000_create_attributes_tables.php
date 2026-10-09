<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What variants differ by. Attributes (Size, Colour) and their values (S, M,
 * Blue) are shared by the whole store; names and labels are JSON objects
 * keyed by language. A product chooses its options (attribute_product), and
 * each variant has exactly one value per attribute: the pivot's primary key
 * is (variant, attribute), and a composite foreign key makes the value
 * belong to that attribute. Attributes and values in use can't be deleted
 * (restrict), even by a variant in the trash, so old variants keep their
 * labels.
 *
 * Every variant gets an attribute signature listing its attribute and value
 * IDs ("3:12;5:40"; empty for the one variant of a product without options),
 * and a partial unique index keeps combinations unique among a product's
 * variants outside the trash (section 13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->jsonb('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
        });
        DB::statement("alter table attributes add constraint attributes_name_translated check (jsonb_typeof(name) = 'object' and name <> '{}'::jsonb)");

        Schema::create('attribute_values', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->jsonb('label');
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['id', 'attribute_id']);
            $table->index(['attribute_id', 'position']);
        });
        DB::statement("alter table attribute_values add constraint attribute_values_label_translated check (jsonb_typeof(label) = 'object' and label <> '{}'::jsonb)");

        Schema::create('attribute_product', static function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['product_id', 'attribute_id']);
            $table->index('attribute_id');
        });

        Schema::create('attribute_value_product_variant', static function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->unsignedBigInteger('attribute_value_id');
            $table->primary(['product_variant_id', 'attribute_id']);
            $table->index('attribute_value_id');
            $table->foreign(['attribute_value_id', 'attribute_id'])->references(['id', 'attribute_id'])->on('attribute_values')->restrictOnDelete();
        });

        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->string('attribute_signature', 255)->default('');
        });
        DB::statement('create unique index product_variants_combination_unique on product_variants (product_id, attribute_signature) where deleted_at is null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists product_variants_combination_unique');
        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->dropColumn('attribute_signature');
        });
        Schema::dropIfExists('attribute_value_product_variant');
        Schema::dropIfExists('attribute_product');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
