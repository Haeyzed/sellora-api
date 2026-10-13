<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Tax classes, tax rates and which class each variant is in (section 3.3).
 *
 * Every store starts with four classes: Standard (the default, also used for
 * shipping), Reduced, Zero-rated (taxable at 0%, still reported) and Exempt
 * (outside tax). Exactly one class is the default; at most one is used for
 * shipping, and none means shipping isn't taxed. No rates are seeded: rates
 * change, and a wrong one is the merchant's liability.
 *
 * A rate is per class and region: a country, or one of its states. An empty
 * state is the country-wide rate, and NULLS NOT DISTINCT makes it count as one
 * value, so a class can't have two country-wide rates in one country. A
 * variant without a row in variant_tax_classes is in the default class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_classes', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->boolean('is_exempt')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('used_for_shipping')->default(false);
            $table->timestampsTz();
        });
        DB::statement('create unique index tax_classes_name_unique on tax_classes (lower(name))');
        DB::statement('create unique index tax_classes_one_default on tax_classes (is_default) where is_default');
        DB::statement('create unique index tax_classes_one_for_shipping on tax_classes (used_for_shipping) where used_for_shipping');

        Schema::create('tax_rates', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tax_class_id')->constrained('tax_classes')->restrictOnDelete();
            $table->char('country_code', 2);
            $table->string('state_code', 10)->nullable();
            $table->string('name', 64);
            $table->decimal('rate', 7, 4)->comment('A percentage: 7.5 means 7.5%');
            $table->timestampsTz();
            $table->unique(['tax_class_id', 'country_code', 'state_code'], 'tax_rates_one_per_class_and_region')->nullsNotDistinct();
            $table->index(['country_code', 'state_code']);
        });
        DB::statement('alter table tax_rates add constraint tax_rates_rate_range check (rate >= 0 and rate <= 100)');
        DB::statement("alter table tax_rates add constraint tax_rates_country_code check (country_code ~ '^[A-Z]{2}$')");

        Schema::create('variant_tax_classes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('tax_class_id')->constrained('tax_classes')->restrictOnDelete();
            $table->timestampsTz();
            $table->index('tax_class_id');
        });

        $now = now();

        DB::table('tax_classes')->insert(array_map(static fn (array $class): array => [
            'public_id' => strtolower((string) Str::ulid()),
            'created_at' => $now,
            'updated_at' => $now,
            ...$class,
        ], [
            ['name' => 'Standard', 'is_exempt' => false, 'is_default' => true, 'used_for_shipping' => true],
            ['name' => 'Reduced', 'is_exempt' => false, 'is_default' => false, 'used_for_shipping' => false],
            ['name' => 'Zero-rated', 'is_exempt' => false, 'is_default' => false, 'used_for_shipping' => false],
            ['name' => 'Exempt', 'is_exempt' => true, 'is_default' => false, 'used_for_shipping' => false],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_tax_classes');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_classes');
    }
};
