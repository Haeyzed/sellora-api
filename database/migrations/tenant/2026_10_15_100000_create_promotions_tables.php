<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Promotions (Step 11): discounts applied automatically or with a code.
 *
 * Amounts are whole minor units in the store's base currency (section
 * 3.3), with optional exact amounts per offered currency; without one, the
 * base amount is converted at the rate the checkout uses. Each type keeps
 * only its own fields, which the checks below enforce. Targets are real
 * foreign keys, removed with the product, variant, category or group, so no
 * promotion points at something that no longer exists.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotions', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->jsonb('title');
            $table->string('method', 10);
            $table->string('type', 20);
            $table->string('applies_to', 10)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->bigInteger('discount_amount')->nullable();
            $table->bigInteger('max_discount_amount')->nullable();
            $table->bigInteger('min_subtotal_amount')->nullable();
            $table->unsignedInteger('min_quantity')->nullable();
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_quantity')->nullable();
            $table->unsignedInteger('max_applications')->nullable();
            $table->boolean('includes_sale_items')->default(false);
            $table->boolean('first_order_only')->default(false);
            $table->boolean('combines_with_product_discounts')->default(false);
            $table->boolean('combines_with_order_discounts')->default(false);
            $table->boolean('combines_with_shipping_discounts')->default(false);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['method', 'is_active']);
        });

        DB::statement(<<<'SQL'
            alter table promotions
                add constraint promotions_method_check check (method in ('automatic', 'code')),
                add constraint promotions_type_check check (type in ('percentage', 'fixed_amount', 'free_shipping', 'buy_x_get_y')),
                add constraint promotions_title_translated check (jsonb_typeof(title) = 'object' and title <> '{}'::jsonb),
                add constraint promotions_percent_range check (percent is null or (percent > 0 and percent <= 100)),
                add constraint promotions_amounts_positive check (
                    (discount_amount is null or discount_amount > 0)
                    and (max_discount_amount is null or max_discount_amount > 0)
                    and (min_subtotal_amount is null or min_subtotal_amount > 0)
                ),
                add constraint promotions_amounts_have_currency check (
                    (currency is null) = (discount_amount is null and max_discount_amount is null and min_subtotal_amount is null)
                ),
                add constraint promotions_counts_positive check (
                    (min_quantity is null or min_quantity > 0) and (buy_quantity is null or buy_quantity > 0)
                    and (get_quantity is null or get_quantity > 0) and (max_applications is null or max_applications > 0)
                    and (usage_limit is null or usage_limit > 0) and (usage_limit_per_customer is null or usage_limit_per_customer > 0)
                ),
                add constraint promotions_ends_after_start check (ends_at is null or ends_at > starts_at),
                add constraint promotions_fields_of_type check (
                    (type = 'percentage' and percent is not null and discount_amount is null and applies_to in ('products', 'order')
                        and buy_quantity is null and get_quantity is null and max_applications is null)
                    or (type = 'fixed_amount' and discount_amount is not null and percent is null and max_discount_amount is null and applies_to in ('products', 'order')
                        and buy_quantity is null and get_quantity is null and max_applications is null)
                    or (type = 'free_shipping' and percent is null and discount_amount is null and applies_to is null
                        and buy_quantity is null and get_quantity is null and max_applications is null)
                    or (type = 'buy_x_get_y' and percent is not null and buy_quantity is not null and get_quantity is not null
                        and discount_amount is null and applies_to is null)
                )
            SQL);

        Schema::create('promotion_currency_amounts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->bigInteger('discount_amount')->nullable();
            $table->bigInteger('max_discount_amount')->nullable();
            $table->bigInteger('min_subtotal_amount')->nullable();
            $table->timestamps();

            $table->unique(['promotion_id', 'currency_code']);
        });

        DB::statement(<<<'SQL'
            alter table promotion_currency_amounts
                add constraint promotion_currency_amounts_currency_code check (currency_code ~ '^[A-Z]{3}$'),
                add constraint promotion_currency_amounts_something_set check (
                    discount_amount is not null or max_discount_amount is not null or min_subtotal_amount is not null
                ),
                add constraint promotion_currency_amounts_positive check (
                    (discount_amount is null or discount_amount > 0)
                    and (max_discount_amount is null or max_discount_amount > 0)
                    and (min_subtotal_amount is null or min_subtotal_amount > 0)
                )
            SQL);

        foreach (['promotion_products' => ['product_id', 'products'], 'promotion_variants' => ['product_variant_id', 'product_variants'], 'promotion_categories' => ['category_id', 'categories']] as $name => [$column, $targets]) {
            Schema::create($name, static function (Blueprint $table) use ($column, $targets): void {
                $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
                $table->foreignId($column)->constrained($targets)->cascadeOnDelete();
                $table->string('list', 10);

                $table->primary(['promotion_id', $column, 'list']);
                $table->index($column);
            });

            DB::statement("alter table {$name} add constraint {$name}_list_check check (list in ('include', 'exclude', 'get'))");
        }

        Schema::create('promotion_customer_groups', static function (Blueprint $table): void {
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained('customer_groups')->cascadeOnDelete();

            $table->primary(['promotion_id', 'customer_group_id']);
            $table->index('customer_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['promotion_customer_groups', 'promotion_categories', 'promotion_variants', 'promotion_products', 'promotion_currency_amounts', 'promotions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
