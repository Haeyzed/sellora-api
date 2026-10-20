<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Credit notes (Step 15b): one per part of a refund that reduces the sale,
 * once that part has gone back, crediting the invoice. A refund that returns
 * money never part of the sale (an overpayment, a payment in review) gets
 * none. Each copies the invoice's seller and buyer, and its lines: returned
 * items, shipping, or a price reduction (an adjustment), each with its taxes.
 *
 * Like invoices, credit notes are tax documents: the database refuses
 * deleting them or changing anything except adding the PDF once and erasure
 * clearing the buyer, only to nothing, once. Credit notes never add up to
 * more than their invoice. A test order's credit notes are numbered
 * "TEST-CN-" and take no number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('reference', 64)->unique()->comment('The public ID of the refund part it credits: one credit note per part');
            $table->string('refund_reference', 64)->comment('The public ID of the refund');
            $table->string('number', 48)->unique()->comment('As printed, such as "CN-2026-000007"; "TEST-CN-1042-1" for a test order');
            $table->string('series', 8)->nullable();
            $table->bigInteger('sequence_number')->nullable();
            $table->boolean('is_test');
            $table->timestampTz('issued_at');
            $table->string('locale', 12);
            $table->char('currency', 3);
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 24, 12);
            $table->string('tax_mode', 16);
            $table->string('seller_name', 255);
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
            $table->bigInteger('net_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount')->comment('What went back for this part, tax included');
            $table->string('pdf_path', 255)->nullable();
            $table->timestampTz('pdf_rendered_at')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['series', 'sequence_number']);
            $table->index('invoice_id');
            $table->index(['issued_at', 'id']);
        });
        DB::statement(<<<'SQL'
            alter table credit_notes
                add constraint credit_notes_test_take_no_number check (
                    is_test = (series is null) and (series is null) = (sequence_number is null)
                    and is_test = (number like 'TEST-%') and (sequence_number is null or sequence_number > 0)
                ),
                add constraint credit_notes_amounts check (total_amount > 0 and tax_amount >= 0 and net_amount >= 0 and net_amount + tax_amount = total_amount),
                add constraint credit_notes_rate_positive check (exchange_rate > 0),
                add constraint credit_notes_pdf_complete check ((pdf_path is null) = (pdf_rendered_at is null)),
                add constraint credit_notes_buyer_erased check (buyer_erased_at is null or (buyer_name is null and buyer_email is null and buyer_phone is null and billing_address is null and shipping_address is null))
            SQL);

        Schema::create('credit_note_lines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->restrictOnDelete();
            $table->smallInteger('position');
            $table->string('kind', 16);
            $table->foreignId('invoice_line_id')->nullable()->comment('The invoice line it credits; null for a price reduction')->constrained('invoice_lines')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('variant_name', 255)->nullable();
            $table->string('sku', 64)->nullable();
            $table->integer('quantity')->comment('Units returned; 0 for a share of a line split across payments');
            $table->char('currency', 3);
            $table->bigInteger('net_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount');
            $table->timestampTz('created_at');
            $table->unique(['credit_note_id', 'position']);
        });
        DB::statement(<<<'SQL'
            alter table credit_note_lines
                add constraint credit_note_lines_kind_check check (kind in ('item', 'shipping', 'adjustment')),
                add constraint credit_note_lines_adjustment_has_no_invoice_line check ((kind = 'adjustment') = (invoice_line_id is null)),
                add constraint credit_note_lines_quantity_not_negative check (quantity >= 0),
                add constraint credit_note_lines_amounts check (net_amount >= 0 and tax_amount >= 0 and net_amount + tax_amount = total_amount)
            SQL);

        Schema::create('credit_note_taxes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->restrictOnDelete();
            $table->foreignId('credit_note_line_id')->constrained('credit_note_lines')->restrictOnDelete();
            $table->string('name', 120);
            $table->char('country_code', 2);
            $table->string('state_code', 50)->nullable();
            $table->decimal('rate_percent', 7, 4);
            $table->char('currency', 3);
            $table->bigInteger('tax_amount');
            $table->timestampTz('created_at');
            $table->index('credit_note_id');
        });
        DB::statement('alter table credit_note_taxes add constraint credit_note_taxes_not_negative check (tax_amount >= 0 and rate_percent >= 0)');

        DB::unprepared(<<<'SQL'
            create function credit_notes_are_tax_documents() returns trigger language plpgsql as $$
            declare
                buyer constant text[] := array['buyer_name', 'buyer_email', 'buyer_phone', 'billing_address', 'shipping_address', 'buyer_erased_at'];
                pdf constant text[] := array['pdf_path', 'pdf_rendered_at'];
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Credit notes are tax documents; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if (to_jsonb(new) - buyer - pdf) is distinct from (to_jsonb(old) - buyer - pdf) then
                    raise exception 'A credit note never changes once issued.' using errcode = 'check_violation';
                end if;

                if old.pdf_path is not null and row(new.pdf_path, new.pdf_rendered_at) is distinct from row(old.pdf_path, old.pdf_rendered_at) then
                    raise exception 'A credit note''s PDF is stored once and never replaced.' using errcode = 'check_violation';
                end if;

                if row(new.buyer_name, new.buyer_email, new.buyer_phone, new.billing_address, new.shipping_address, new.buyer_erased_at)
                    is distinct from row(old.buyer_name, old.buyer_email, old.buyer_phone, old.billing_address, old.shipping_address, old.buyer_erased_at) then
                    if coalesce(current_setting('app.erasing_personal_data', true), '') <> 'on' then
                        raise exception 'A credit note''s buyer details change only through erasure.' using errcode = 'check_violation';
                    end if;

                    if old.buyer_erased_at is not null or new.buyer_erased_at is null then
                        raise exception 'Erasure only clears a credit note''s buyer details, once.' using errcode = 'check_violation';
                    end if;
                end if;

                return new;
            end;
            $$;

            create trigger credit_notes_are_tax_documents before update or delete on credit_notes
                for each row execute function credit_notes_are_tax_documents();
            create trigger credit_notes_are_never_emptied before truncate on credit_notes
                for each statement execute function credit_notes_are_tax_documents();

            create function credit_notes_within_invoice() returns trigger language plpgsql as $$
            begin
                if (select coalesce(sum(total_amount), 0) from credit_notes where invoice_id = new.invoice_id) + new.total_amount
                    > (select total_amount from invoices where id = new.invoice_id) then
                    raise exception 'Credit notes never add up to more than their invoice.' using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;

            create trigger credit_notes_within_invoice before insert on credit_notes
                for each row execute function credit_notes_within_invoice();
            SQL);

        foreach (['credit_note_lines', 'credit_note_taxes'] as $table) {
            DB::unprepared("create trigger {$table}_never_change before update or delete on {$table} for each row execute function order_records_never_change();");
            DB::unprepared("create trigger {$table}_never_emptied before truncate on {$table} for each statement execute function order_records_never_change();");
        }
    }

    public function down(): void
    {
        foreach (['credit_note_taxes', 'credit_note_lines', 'credit_notes'] as $table) {
            Schema::dropIfExists($table);
        }

        DB::unprepared('drop function if exists credit_notes_are_tax_documents() cascade; drop function if exists credit_notes_within_invoice() cascade;');
    }
};
