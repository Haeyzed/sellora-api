<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tax invoices (Step 15b): issued once an order is paid in full, at most one
 * per order, each a full copy (seller, buyer, addresses, lines, each tax,
 * currency and rate) so nothing is read live to show or render it.
 *
 * Invoices are tax documents: the database refuses deleting them or changing
 * anything, with two exceptions. Their PDF is added once, when rendered; and
 * their buyer's details are cleared, only to nothing, only during erasure
 * (app.erasing_personal_data), which Orders does once the store's tax-record
 * retention ends. Their lines and taxes never change.
 *
 * A test order's invoice is numbered "TEST-" and its order number, and takes
 * no number from the store's series; every other invoice's number is unique
 * in its series.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('number', 40)->unique()->comment('As printed, such as "INV-2026-000042"; "TEST-1042" for a test order');
            $table->string('series', 8)->nullable()->comment('"2026", or "all" when numbered continuously; null for a test order');
            $table->bigInteger('sequence_number')->nullable();
            $table->boolean('is_test');
            $table->timestampTz('issued_at');
            $table->timestampTz('paid_at')->comment('When the order was paid in full: the date of the sale');
            $table->string('locale', 12);
            $table->char('currency', 3);
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 24, 12)->comment('Base amount × rate = amount in its currency');
            $table->string('tax_mode', 16);
            $table->char('tax_country_code', 2);
            $table->string('tax_state_code', 100)->nullable();
            $table->string('seller_name', 255)->comment('The store\'s legal name, or its name');
            $table->string('seller_trading_name', 255);
            $table->string('seller_tax_registration_label', 40)->nullable();
            $table->string('seller_tax_registration_number', 64)->nullable();
            $table->jsonb('seller_address')->nullable();
            $table->string('seller_email', 254)->nullable();
            $table->string('seller_phone', 32)->nullable();
            $table->string('buyer_name', 200)->nullable()->comment('Null once erased');
            $table->string('buyer_email', 254)->nullable();
            $table->string('buyer_phone', 32)->nullable();
            $table->jsonb('billing_address')->nullable();
            $table->jsonb('shipping_address')->nullable();
            $table->timestampTz('buyer_erased_at')->nullable();
            $table->text('footer')->nullable();
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount');
            $table->bigInteger('shipping_amount');
            $table->bigInteger('shipping_discount_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount');
            $table->bigInteger('base_total_amount');
            $table->string('pdf_path', 255)->nullable()->comment('Where its PDF is stored, privately; null until rendered');
            $table->timestampTz('pdf_rendered_at')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['series', 'sequence_number']);
            $table->index(['issued_at', 'id']);
        });
        DB::statement(<<<'SQL'
            alter table invoices
                add constraint invoices_test_take_no_number check (
                    is_test = (series is null) and (series is null) = (sequence_number is null)
                    and is_test = (number like 'TEST-%') and (sequence_number is null or sequence_number > 0)
                ),
                add constraint invoices_amounts_not_negative check (subtotal_amount >= 0 and discount_amount >= 0 and shipping_amount >= 0 and shipping_discount_amount >= 0 and tax_amount >= 0 and total_amount >= 0 and base_total_amount >= 0),
                add constraint invoices_rate_positive check (exchange_rate > 0),
                add constraint invoices_pdf_complete check ((pdf_path is null) = (pdf_rendered_at is null)),
                add constraint invoices_buyer_erased check (buyer_erased_at is null or (buyer_name is null and buyer_email is null and buyer_phone is null and billing_address is null and shipping_address is null))
            SQL);

        Schema::create('invoice_lines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->smallInteger('position');
            $table->string('kind', 16);
            $table->foreignId('order_line_id')->nullable()->comment('Null for the shipping')->constrained('order_lines')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('variant_name', 255)->nullable();
            $table->string('sku', 64)->nullable();
            $table->integer('quantity');
            $table->char('currency', 3);
            $table->bigInteger('unit_price_amount');
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount');
            $table->bigInteger('net_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount');
            $table->timestampTz('created_at');
            $table->unique(['invoice_id', 'position']);
        });
        DB::statement(<<<'SQL'
            alter table invoice_lines
                add constraint invoice_lines_kind_check check (kind in ('item', 'shipping')),
                add constraint invoice_lines_shipping_has_no_order_line check ((kind = 'shipping') = (order_line_id is null)),
                add constraint invoice_lines_quantity_positive check (quantity > 0),
                add constraint invoice_lines_amounts_not_negative check (unit_price_amount >= 0 and discount_amount >= 0 and net_amount >= 0 and tax_amount >= 0),
                add constraint invoice_lines_net_and_tax check (net_amount + tax_amount = total_amount)
            SQL);

        Schema::create('invoice_taxes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('invoice_line_id')->constrained('invoice_lines')->restrictOnDelete();
            $table->string('name', 120);
            $table->char('country_code', 2);
            $table->string('state_code', 50)->nullable();
            $table->decimal('rate_percent', 7, 4)->comment('7.5 means 7.5%');
            $table->char('currency', 3);
            $table->bigInteger('tax_amount');
            $table->timestampTz('created_at');
            $table->index('invoice_id');
        });
        DB::statement('alter table invoice_taxes add constraint invoice_taxes_not_negative check (tax_amount >= 0 and rate_percent >= 0)');

        DB::unprepared(<<<'SQL'
            create function invoices_are_tax_documents() returns trigger language plpgsql as $$
            declare
                buyer constant text[] := array['buyer_name', 'buyer_email', 'buyer_phone', 'billing_address', 'shipping_address', 'buyer_erased_at'];
                pdf constant text[] := array['pdf_path', 'pdf_rendered_at'];
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Invoices are tax documents; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if (to_jsonb(new) - buyer - pdf) is distinct from (to_jsonb(old) - buyer - pdf) then
                    raise exception 'An invoice never changes once issued; corrections are credit notes.' using errcode = 'check_violation';
                end if;

                if old.pdf_path is not null and row(new.pdf_path, new.pdf_rendered_at) is distinct from row(old.pdf_path, old.pdf_rendered_at) then
                    raise exception 'An invoice''s PDF is stored once and never replaced.' using errcode = 'check_violation';
                end if;

                if row(new.buyer_name, new.buyer_email, new.buyer_phone, new.billing_address, new.shipping_address, new.buyer_erased_at)
                    is distinct from row(old.buyer_name, old.buyer_email, old.buyer_phone, old.billing_address, old.shipping_address, old.buyer_erased_at) then
                    if coalesce(current_setting('app.erasing_personal_data', true), '') <> 'on' then
                        raise exception 'An invoice''s buyer details change only through erasure.' using errcode = 'check_violation';
                    end if;

                    if old.buyer_erased_at is not null or new.buyer_erased_at is null then
                        raise exception 'Erasure only clears an invoice''s buyer details, once.' using errcode = 'check_violation';
                    end if;
                end if;

                return new;
            end;
            $$;

            create trigger invoices_are_tax_documents before update or delete on invoices
                for each row execute function invoices_are_tax_documents();
            create trigger invoices_are_never_emptied before truncate on invoices
                for each statement execute function invoices_are_tax_documents();
            SQL);

        foreach (['invoice_lines', 'invoice_taxes'] as $table) {
            DB::unprepared("create trigger {$table}_never_change before update or delete on {$table} for each row execute function order_records_never_change();");
            DB::unprepared("create trigger {$table}_never_emptied before truncate on {$table} for each statement execute function order_records_never_change();");
        }
    }

    public function down(): void
    {
        foreach (['invoice_taxes', 'invoice_lines', 'invoices'] as $table) {
            Schema::dropIfExists($table);
        }

        DB::unprepared('drop function if exists invoices_are_tax_documents() cascade;');
    }
};
