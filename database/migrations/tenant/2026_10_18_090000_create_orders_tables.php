<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Orders (Step 14, section 13): the record of a sale, copied at placement so
 * nothing later changes it.
 *
 * An order keeps its number (a per-store sequence, gaps allowed), the buyer's
 * contact details as given, the currency and the exchange rate it used, the
 * tax mode and destination, and its totals in whole minor units. Its lines,
 * each discount per promotion, each tax applied and its shipping line are
 * copies too. It has three statuses: the order's (open, completed,
 * cancelled), payment (moved only by Payments) and fulfilment (from its
 * shipments).
 *
 * Financial records are never changed or deleted (section 3.3), so triggers
 * refuse it: lines, discounts, taxes, the shipping line and the order's
 * history are never updated or deleted; an order is never deleted, and of
 * its columns only the statuses, their timestamps and the guest-to-customer
 * link (once) may change. Its contact details, note and addresses change
 * only during erasure (app.erasing_personal_data, set by the Shared erasure
 * runner); corrections are new versions instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create sequence order_numbers start with '.max(1, config()->integer('orders.first_number')));

        Schema::create('orders', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->bigInteger('number')->unique()->default(DB::raw("nextval('order_numbers')"));
            $table->string('checkout_id', 64)->unique()->comment('The checkout it was placed from: placing it again returns this order');
            $table->string('status', 16)->default('open');
            $table->string('payment_status', 24)->default('unpaid');
            $table->string('fulfilment_status', 24);
            $table->boolean('refund_due')->default(false)->comment('Paid, but cancelled: the payment is owed back (Payments, Step 15)');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('contact_email', 254)->comment('In lower case, as given at checkout');
            $table->string('contact_name', 200)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('locale', 12)->comment('The language its names were copied in');
            $table->char('currency', 3);
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 24, 12)->comment('Base amount × rate = amount in the order currency');
            $table->decimal('market_rate', 24, 12)->nullable();
            $table->decimal('margin_percent', 6, 4)->default(0);
            $table->string('rate_source', 16);
            $table->timestampTz('rate_as_of')->nullable();
            $table->string('tax_mode', 16);
            $table->string('tax_calculated_by', 64);
            $table->char('tax_country_code', 2);
            $table->string('tax_state_code', 100)->nullable();
            $table->string('tax_postal_code', 20)->nullable();
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount');
            $table->bigInteger('shipping_amount')->comment('Before free-shipping discounts');
            $table->bigInteger('shipping_discount_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount');
            $table->bigInteger('base_total_amount')->comment('The total in the base currency at the order\'s rate');
            $table->text('customer_note')->nullable();
            $table->timestampTz('placed_at');
            $table->timestampTz('payment_due_at');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason', 24)->nullable();
            $table->string('cancelled_by_type', 64)->nullable();
            $table->string('cancelled_by_id', 64)->nullable();
            $table->timestampsTz();
            $table->index(['placed_at', 'id']);
            $table->index(['customer_id', 'placed_at']);
            $table->index('contact_email');
        });
        DB::statement("create index orders_awaiting_payment on orders (payment_due_at) where status = 'open' and payment_status = 'unpaid'");
        DB::statement(<<<'SQL'
            alter table orders
                add constraint orders_status_check check (status in ('open', 'completed', 'cancelled')),
                add constraint orders_payment_status_check check (payment_status in ('unpaid', 'paid')),
                add constraint orders_fulfilment_status_check check (fulfilment_status in ('unfulfilled', 'partially_fulfilled', 'fulfilled', 'not_required')),
                add constraint orders_cancellation_reason_check check (cancellation_reason in ('staff', 'customer', 'payment_expired')),
                add constraint orders_email_lower_case check (contact_email = lower(contact_email)),
                add constraint orders_amounts_not_negative check (subtotal_amount >= 0 and discount_amount >= 0 and shipping_amount >= 0 and shipping_discount_amount >= 0 and tax_amount >= 0 and total_amount >= 0 and base_total_amount >= 0),
                add constraint orders_discounts_within check (discount_amount <= subtotal_amount and shipping_discount_amount <= shipping_amount),
                add constraint orders_rate_positive check (exchange_rate > 0),
                add constraint orders_paid_when_paid check ((payment_status = 'paid') = (paid_at is not null)),
                add constraint orders_cancelled_when_cancelled check ((status = 'cancelled') = (cancelled_at is not null and cancellation_reason is not null)),
                add constraint orders_completed_when_done check ((status = 'completed') = (completed_at is not null) and (status <> 'completed' or (payment_status = 'paid' and fulfilment_status in ('fulfilled', 'not_required')))),
                add constraint orders_refund_due_only_when_paid_and_cancelled check (not refund_due or (payment_status = 'paid' and status = 'cancelled')),
                add constraint orders_canceller_complete check ((cancelled_by_type is null) = (cancelled_by_id is null))
            SQL);

        Schema::create('order_lines', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->smallInteger('position');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('product_name', 255);
            $table->string('variant_name', 255)->nullable()->comment('Its option values, such as "Blue / M"; null without options');
            $table->string('sku', 64)->nullable();
            $table->boolean('requires_shipping');
            $table->integer('weight_grams')->nullable();
            $table->string('tax_class_id', 26)->nullable();
            $table->string('tax_class_name', 120);
            $table->string('tax_outcome', 16);
            $table->integer('quantity');
            $table->char('currency', 3);
            $table->bigInteger('unit_price_amount');
            $table->boolean('price_is_fixed')->comment('The merchant set this price for the currency; otherwise converted');
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount');
            $table->bigInteger('net_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount');
            $table->timestampTz('created_at');
            $table->unique(['order_id', 'position']);
            $table->index('product_variant_id');
        });
        DB::statement(<<<'SQL'
            alter table order_lines
                add constraint order_lines_quantity_positive check (quantity > 0),
                add constraint order_lines_amounts_not_negative check (unit_price_amount >= 0 and discount_amount >= 0 and net_amount >= 0 and tax_amount >= 0),
                add constraint order_lines_subtotal check (subtotal_amount = unit_price_amount * quantity),
                add constraint order_lines_discount_within check (discount_amount <= subtotal_amount),
                add constraint order_lines_net_and_tax check (net_amount + tax_amount = total_amount),
                add constraint order_lines_tax_outcome_check check (tax_outcome in ('taxed', 'zero_rated', 'exempt', 'no_rate'))
            SQL);

        Schema::create('order_discounts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_line_id')->nullable()->comment('Null for a discount on shipping')->constrained('order_lines')->restrictOnDelete();
            $table->string('source', 32)->comment('Where it came from, such as "promotion"');
            $table->string('reference', 64)->comment('What it is in that source, such as the promotion\'s public ID');
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->restrictOnDelete();
            $table->string('code', 64)->nullable()->comment('The code entered, when it came from one');
            $table->string('label', 255);
            $table->string('class', 16);
            $table->char('currency', 3);
            $table->bigInteger('discount_amount');
            $table->timestampTz('created_at');
            $table->index('order_id');
            $table->index('promotion_id');
        });
        DB::statement(<<<'SQL'
            alter table order_discounts
                add constraint order_discounts_positive check (discount_amount > 0),
                add constraint order_discounts_class_check check (class in ('product', 'order', 'shipping')),
                add constraint order_discounts_shipping_has_no_line check ((class = 'shipping') = (order_line_id is null))
            SQL);

        Schema::create('order_taxes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_line_id')->nullable()->comment('Null for tax on shipping')->constrained('order_lines')->restrictOnDelete();
            $table->string('name', 120);
            $table->char('country_code', 2);
            $table->string('state_code', 50)->nullable();
            $table->decimal('rate_percent', 7, 4)->comment('7.5 means 7.5%');
            $table->char('currency', 3);
            $table->bigInteger('tax_amount');
            $table->timestampTz('created_at');
            $table->index('order_id');
        });
        DB::statement('alter table order_taxes add constraint order_taxes_not_negative check (tax_amount >= 0 and rate_percent >= 0)');

        Schema::create('order_shipping', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->restrictOnDelete();
            $table->string('method_name', 255);
            $table->string('fulfilment', 16);
            $table->string('carrier', 64)->nullable();
            $table->string('carrier_service', 64)->nullable();
            $table->char('pickup_country_code', 2)->nullable();
            $table->string('pickup_state_code', 100)->nullable();
            $table->string('pickup_address_line1', 255)->nullable();
            $table->string('pickup_city', 120)->nullable();
            $table->string('pickup_postal_code', 20)->nullable();
            $table->char('currency', 3);
            $table->bigInteger('price_amount')->comment('Before free-shipping discounts');
            $table->bigInteger('discount_amount');
            $table->bigInteger('net_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount')->comment('What was charged for it, tax included');
            $table->string('tax_outcome', 16)->nullable();
            $table->smallInteger('min_days')->nullable();
            $table->smallInteger('max_days')->nullable();
            $table->date('earliest_on')->nullable();
            $table->date('latest_on')->nullable();
            $table->timestampTz('created_at');
        });
        DB::statement(<<<'SQL'
            alter table order_shipping
                add constraint order_shipping_fulfilment_check check (fulfilment in ('ship', 'own_delivery', 'pickup')),
                add constraint order_shipping_amounts_not_negative check (price_amount >= 0 and discount_amount >= 0 and net_amount >= 0 and tax_amount >= 0),
                add constraint order_shipping_discount_within check (discount_amount <= price_amount),
                add constraint order_shipping_net_and_tax check (net_amount + tax_amount = total_amount),
                add constraint order_shipping_estimate_pair check ((min_days is null) = (max_days is null) and (earliest_on is null) = (min_days is null) and (latest_on is null) = (min_days is null))
            SQL);

        Schema::create('order_addresses', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('kind', 16);
            $table->smallInteger('version')->comment('1 as placed; each correction adds the next');
            $table->string('recipient_name', 200)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('line1', 255)->nullable();
            $table->string('line2', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state_code', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2);
            $table->string('phone', 32)->nullable();
            $table->string('created_by_type', 64)->nullable()->comment('Who corrected it; null as placed');
            $table->string('created_by_id', 64)->nullable();
            $table->timestampTz('created_at');
            $table->unique(['order_id', 'kind', 'version']);
        });
        DB::statement(<<<'SQL'
            alter table order_addresses
                add constraint order_addresses_kind_check check (kind in ('billing', 'shipping')),
                add constraint order_addresses_version_positive check (version > 0),
                add constraint order_addresses_creator_complete check ((created_by_type is null) = (created_by_id is null))
            SQL);

        Schema::create('order_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('type', 48);
            $table->jsonb('data')->nullable()->comment('What changed; never contact details or addresses');
            $table->string('actor_type', 64)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->timestampTz('created_at');
            $table->index(['order_id', 'id']);
        });
        DB::statement('alter table order_events add constraint order_events_actor_complete check ((actor_type is null) = (actor_id is null))');

        DB::unprepared(<<<'SQL'
            create function orders_are_financial_records() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Orders are financial records; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if row(new.id, new.public_id, new.number, new.checkout_id, new.locale, new.currency, new.base_currency, new.exchange_rate, new.market_rate,
                        new.margin_percent, new.rate_source, new.rate_as_of, new.tax_mode, new.tax_calculated_by, new.tax_country_code, new.tax_state_code,
                        new.tax_postal_code, new.subtotal_amount, new.discount_amount, new.shipping_amount, new.shipping_discount_amount, new.tax_amount,
                        new.total_amount, new.base_total_amount, new.placed_at, new.payment_due_at, new.created_at)
                    is distinct from row(old.id, old.public_id, old.number, old.checkout_id, old.locale, old.currency, old.base_currency, old.exchange_rate,
                        old.market_rate, old.margin_percent, old.rate_source, old.rate_as_of, old.tax_mode, old.tax_calculated_by, old.tax_country_code,
                        old.tax_state_code, old.tax_postal_code, old.subtotal_amount, old.discount_amount, old.shipping_amount, old.shipping_discount_amount,
                        old.tax_amount, old.total_amount, old.base_total_amount, old.placed_at, old.payment_due_at, old.created_at) then
                    raise exception 'An order''s number, amounts, rates and placement never change.' using errcode = 'check_violation';
                end if;

                if old.customer_id is not null and new.customer_id is distinct from old.customer_id then
                    raise exception 'An order''s customer never changes once set.' using errcode = 'check_violation';
                end if;

                if row(new.contact_email, new.contact_name, new.contact_phone, new.customer_note)
                        is distinct from row(old.contact_email, old.contact_name, old.contact_phone, old.customer_note)
                    and coalesce(current_setting('app.erasing_personal_data', true), '') <> 'on' then
                    raise exception 'An order''s contact details change only through erasure; corrections are new versions.' using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;

            create trigger orders_are_financial_records before update or delete on orders
                for each row execute function orders_are_financial_records();
            create trigger orders_are_never_emptied before truncate on orders
                for each statement execute function orders_are_financial_records();

            create function order_records_never_change() returns trigger language plpgsql as $$
            begin
                raise exception '% rows are financial records; % is refused.', tg_table_name, tg_op using errcode = 'check_violation';
            end;
            $$;

            create function order_personal_records_change_only_in_erasure() returns trigger language plpgsql as $$
            begin
                if coalesce(current_setting('app.erasing_personal_data', true), '') <> 'on' then
                    raise exception '% rows change only through erasure; corrections are new versions.', tg_table_name using errcode = 'check_violation';
                end if;

                return case when tg_op = 'DELETE' then old when tg_op = 'UPDATE' then new else null end;
            end;
            $$;
            SQL);

        foreach (['order_lines', 'order_discounts', 'order_taxes', 'order_shipping', 'order_events'] as $table) {
            DB::unprepared("create trigger {$table}_never_change before update or delete on {$table} for each row execute function order_records_never_change();");
            DB::unprepared("create trigger {$table}_never_emptied before truncate on {$table} for each statement execute function order_records_never_change();");
        }

        DB::unprepared('create trigger order_addresses_change_only_in_erasure before update or delete on order_addresses for each row execute function order_personal_records_change_only_in_erasure();');
        DB::unprepared('create trigger order_addresses_never_emptied before truncate on order_addresses for each statement execute function order_records_never_change();');
    }

    public function down(): void
    {
        foreach (['order_events', 'order_addresses', 'order_shipping', 'order_taxes', 'order_discounts', 'order_lines', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }

        DB::unprepared('drop function if exists orders_are_financial_records(); drop function if exists order_records_never_change(); drop function if exists order_personal_records_change_only_in_erasure(); drop sequence if exists order_numbers;');
    }
};
