<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * An order comes from exactly one place (Step 18c): a checkout, or a return
 * it replaces in an exchange. A return has at most one replacement order
 * that isn't cancelled: one cancelled or expired unpaid frees the return for
 * another. Like the checkout, the return an order replaces never changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->string('checkout_id', 64)->nullable()->comment('The checkout it was placed from: placing it again returns this order. Null for a replacement order')->change();
            $table->foreignId('replaces_return_id')->nullable()->after('checkout_id')
                ->comment('The return it replaces in an exchange; null for an order placed at checkout. Exactly one of this and checkout_id is set')
                ->constrained('returns')->restrictOnDelete();
        });

        DB::statement('alter table orders add constraint orders_placed_from_one_source check ((checkout_id is null) <> (replaces_return_id is null))');
        DB::statement("create unique index orders_one_live_replacement_per_return on orders (replaces_return_id) where replaces_return_id is not null and status <> 'cancelled'");

        $this->freeze(withReplacedReturn: true);
    }

    public function down(): void
    {
        $this->freeze(withReplacedReturn: false);

        DB::statement('drop index orders_one_live_replacement_per_return');
        DB::statement('alter table orders drop constraint orders_placed_from_one_source');

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replaces_return_id');
            $table->string('checkout_id', 64)->nullable(false)->comment('The checkout it was placed from: placing it again returns this order')->change();
        });
    }

    /**
     * The orders trigger as the buyer tax ID migration left it, with the return an order replaces among what never changes.
     */
    private function freeze(bool $withReplacedReturn): void
    {
        $new = $withReplacedReturn ? ', new.replaces_return_id' : '';
        $old = $withReplacedReturn ? ', old.replaces_return_id' : '';
        $source = $withReplacedReturn ? 'source, ' : '';

        DB::unprepared(<<<SQL
            create or replace function orders_are_financial_records() returns trigger language plpgsql as \$\$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Orders are financial records; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if row(new.id, new.public_id, new.number, new.checkout_id{$new}, new.locale, new.currency, new.base_currency, new.exchange_rate, new.market_rate,
                        new.margin_percent, new.rate_source, new.rate_as_of, new.tax_mode, new.tax_calculated_by, new.tax_country_code, new.tax_state_code,
                        new.tax_postal_code, new.subtotal_amount, new.discount_amount, new.shipping_amount, new.shipping_discount_amount, new.tax_amount,
                        new.total_amount, new.base_total_amount, new.placed_at, new.payment_due_at, new.created_at, new.is_test, new.pay_on_delivery)
                    is distinct from row(old.id, old.public_id, old.number, old.checkout_id{$old}, old.locale, old.currency, old.base_currency, old.exchange_rate,
                        old.market_rate, old.margin_percent, old.rate_source, old.rate_as_of, old.tax_mode, old.tax_calculated_by, old.tax_country_code,
                        old.tax_state_code, old.tax_postal_code, old.subtotal_amount, old.discount_amount, old.shipping_amount, old.shipping_discount_amount,
                        old.tax_amount, old.total_amount, old.base_total_amount, old.placed_at, old.payment_due_at, old.created_at, old.is_test, old.pay_on_delivery) then
                    raise exception 'An order''s number, {$source}amounts, rates, placement, test flag and way of paying never change.' using errcode = 'check_violation';
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
            \$\$;
            SQL);
    }
};
