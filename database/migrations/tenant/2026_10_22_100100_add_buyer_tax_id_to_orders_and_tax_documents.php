<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The buyer's tax ID (Step 16), given at checkout when the store asks for
 * one: copied onto the order, and from it onto its invoice and credit
 * notes. It is the buyer's personal data, like their contact details, so it
 * changes only through erasure: anonymising the order clears it, and a tax
 * document keeps it until its buyer details are erased.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders', 'invoices', 'credit_notes'] as $table) {
            Schema::table($table, static function (Blueprint $table): void {
                $table->string('buyer_tax_id', 32)->nullable()->comment('The buyer\'s tax ID, as given at checkout');
                $table->char('buyer_tax_id_country', 2)->nullable()->comment('The country that issued it, ISO 3166-1 alpha-2');
            });
        }

        DB::statement('alter table orders add constraint orders_buyer_tax_id_with_country check ((buyer_tax_id is null) = (buyer_tax_id_country is null))');
        DB::statement('alter table invoices add constraint invoices_buyer_tax_id_with_country check ((buyer_tax_id is null) = (buyer_tax_id_country is null))');
        DB::statement('alter table credit_notes add constraint credit_notes_buyer_tax_id_with_country check ((buyer_tax_id is null) = (buyer_tax_id_country is null))');

        DB::statement('alter table invoices drop constraint invoices_buyer_erased');
        DB::statement('alter table invoices add constraint invoices_buyer_erased check (buyer_erased_at is null or (buyer_name is null and buyer_email is null and buyer_phone is null and buyer_tax_id is null and buyer_tax_id_country is null and billing_address is null and shipping_address is null))');
        DB::statement('alter table credit_notes drop constraint credit_notes_buyer_erased');
        DB::statement('alter table credit_notes add constraint credit_notes_buyer_erased check (buyer_erased_at is null or (buyer_name is null and buyer_email is null and buyer_phone is null and buyer_tax_id is null and buyer_tax_id_country is null and billing_address is null and shipping_address is null))');

        // The buyer's tax ID joins the order's contact details, which change only through erasure.
        DB::unprepared(<<<'SQL'
            create or replace function orders_are_financial_records() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Orders are financial records; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if row(new.id, new.public_id, new.number, new.checkout_id, new.locale, new.currency, new.base_currency, new.exchange_rate, new.market_rate,
                        new.margin_percent, new.rate_source, new.rate_as_of, new.tax_mode, new.tax_calculated_by, new.tax_country_code, new.tax_state_code,
                        new.tax_postal_code, new.subtotal_amount, new.discount_amount, new.shipping_amount, new.shipping_discount_amount, new.tax_amount,
                        new.total_amount, new.base_total_amount, new.placed_at, new.payment_due_at, new.created_at, new.is_test, new.pay_on_delivery)
                    is distinct from row(old.id, old.public_id, old.number, old.checkout_id, old.locale, old.currency, old.base_currency, old.exchange_rate,
                        old.market_rate, old.margin_percent, old.rate_source, old.rate_as_of, old.tax_mode, old.tax_calculated_by, old.tax_country_code,
                        old.tax_state_code, old.tax_postal_code, old.subtotal_amount, old.discount_amount, old.shipping_amount, old.shipping_discount_amount,
                        old.tax_amount, old.total_amount, old.base_total_amount, old.placed_at, old.payment_due_at, old.created_at, old.is_test, old.pay_on_delivery) then
                    raise exception 'An order''s number, amounts, rates, placement, test flag and way of paying never change.' using errcode = 'check_violation';
                end if;

                if old.customer_id is not null and new.customer_id is distinct from old.customer_id then
                    raise exception 'An order''s customer never changes once set.' using errcode = 'check_violation';
                end if;

                if row(new.contact_email, new.contact_name, new.contact_phone, new.customer_note, new.buyer_tax_id, new.buyer_tax_id_country)
                        is distinct from row(old.contact_email, old.contact_name, old.contact_phone, old.customer_note, old.buyer_tax_id, old.buyer_tax_id_country)
                    and coalesce(current_setting('app.erasing_personal_data', true), '') <> 'on' then
                    raise exception 'An order''s contact details change only through erasure; corrections are new versions.' using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;
            SQL);

        // And a tax document's buyer details, which change only through erasure, once.
        DB::unprepared(<<<'SQL'
            create or replace function invoices_are_tax_documents() returns trigger language plpgsql as $$
            declare
                buyer constant text[] := array['buyer_name', 'buyer_email', 'buyer_phone', 'buyer_tax_id', 'buyer_tax_id_country', 'billing_address', 'shipping_address', 'buyer_erased_at'];
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

                if row(new.buyer_name, new.buyer_email, new.buyer_phone, new.buyer_tax_id, new.buyer_tax_id_country, new.billing_address, new.shipping_address, new.buyer_erased_at)
                    is distinct from row(old.buyer_name, old.buyer_email, old.buyer_phone, old.buyer_tax_id, old.buyer_tax_id_country, old.billing_address, old.shipping_address, old.buyer_erased_at) then
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

            create or replace function credit_notes_are_tax_documents() returns trigger language plpgsql as $$
            declare
                buyer constant text[] := array['buyer_name', 'buyer_email', 'buyer_phone', 'buyer_tax_id', 'buyer_tax_id_country', 'billing_address', 'shipping_address', 'buyer_erased_at'];
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

                if row(new.buyer_name, new.buyer_email, new.buyer_phone, new.buyer_tax_id, new.buyer_tax_id_country, new.billing_address, new.shipping_address, new.buyer_erased_at)
                    is distinct from row(old.buyer_name, old.buyer_email, old.buyer_phone, old.buyer_tax_id, old.buyer_tax_id_country, old.billing_address, old.shipping_address, old.buyer_erased_at) then
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
            SQL);
    }

    public function down(): void
    {
        // The functions as the payment-method (orders) and tax-document migrations left them.
        DB::unprepared(<<<'SQL'
            create or replace function orders_are_financial_records() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Orders are financial records; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if row(new.id, new.public_id, new.number, new.checkout_id, new.locale, new.currency, new.base_currency, new.exchange_rate, new.market_rate,
                        new.margin_percent, new.rate_source, new.rate_as_of, new.tax_mode, new.tax_calculated_by, new.tax_country_code, new.tax_state_code,
                        new.tax_postal_code, new.subtotal_amount, new.discount_amount, new.shipping_amount, new.shipping_discount_amount, new.tax_amount,
                        new.total_amount, new.base_total_amount, new.placed_at, new.payment_due_at, new.created_at, new.is_test, new.pay_on_delivery)
                    is distinct from row(old.id, old.public_id, old.number, old.checkout_id, old.locale, old.currency, old.base_currency, old.exchange_rate,
                        old.market_rate, old.margin_percent, old.rate_source, old.rate_as_of, old.tax_mode, old.tax_calculated_by, old.tax_country_code,
                        old.tax_state_code, old.tax_postal_code, old.subtotal_amount, old.discount_amount, old.shipping_amount, old.shipping_discount_amount,
                        old.tax_amount, old.total_amount, old.base_total_amount, old.placed_at, old.payment_due_at, old.created_at, old.is_test, old.pay_on_delivery) then
                    raise exception 'An order''s number, amounts, rates, placement, test flag and way of paying never change.' using errcode = 'check_violation';
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

            create or replace function invoices_are_tax_documents() returns trigger language plpgsql as $$
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

            create or replace function credit_notes_are_tax_documents() returns trigger language plpgsql as $$
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
            SQL);

        DB::statement('alter table invoices drop constraint invoices_buyer_erased');
        DB::statement('alter table invoices add constraint invoices_buyer_erased check (buyer_erased_at is null or (buyer_name is null and buyer_email is null and buyer_phone is null and billing_address is null and shipping_address is null))');
        DB::statement('alter table credit_notes drop constraint credit_notes_buyer_erased');
        DB::statement('alter table credit_notes add constraint credit_notes_buyer_erased check (buyer_erased_at is null or (buyer_name is null and buyer_email is null and buyer_phone is null and billing_address is null and shipping_address is null))');

        foreach (['orders', 'invoices', 'credit_notes'] as $table) {
            Schema::table($table, static fn (Blueprint $table) => $table->dropColumn(['buyer_tax_id', 'buyer_tax_id_country']));
        }
    }
};
