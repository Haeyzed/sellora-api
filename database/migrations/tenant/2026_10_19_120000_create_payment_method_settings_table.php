<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Payment methods paid by hand (Step 15.4): bank transfer and cash on
 * delivery. Each store turns them on, sets how long a buyer has to pay
 * (bank transfer 24 hours to 7 days, 72 hours unless changed; cash on
 * delivery 3 to 30 days to get the order out of the door, 14 unless
 * changed) and what the buyer is told. The limits live in config
 * (payments.manual_methods), so only sense is checked here.
 *
 * An order paid on delivery says so from placement, for good: it may ship
 * before it is paid, and once a shipment exists it no longer expires. Staff
 * are alerted, once, when cash hasn't been recorded as collected a while
 * after delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->boolean('pay_on_delivery')->default(false)->comment('Paid in cash on delivery: may ship before it is paid');
        });
        DB::statement('alter table orders add constraint orders_pay_on_delivery_never_test check (not (pay_on_delivery and is_test))');
        $this->freezePayOnDelivery();

        Schema::create('payment_method_settings', static function (Blueprint $table): void {
            $table->id();
            $table->string('method', 24)->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('deadline_hours');
            $table->unsignedSmallInteger('collection_alert_hours')->nullable()->comment('Cash on delivery only: alert when cash is not recorded this long after delivery');
            $table->text('instructions')->nullable()->comment('What the buyer is told, such as the account to pay into');
            $table->timestampsTz();
        });
        DB::statement(<<<'SQL'
            alter table payment_method_settings
                add constraint payment_method_settings_method_check check (method in ('bank_transfer', 'cash_on_delivery')),
                add constraint payment_method_settings_deadline_positive check (deadline_hours > 0),
                add constraint payment_method_settings_alert_only_on_delivery check (
                    (method = 'cash_on_delivery') = (collection_alert_hours is not null) and (collection_alert_hours is null or collection_alert_hours > 0)
                )
            SQL);

        Schema::table('payments', static function (Blueprint $table): void {
            $table->timestampTz('collection_alerted_at')->nullable()->comment('When staff were told cash was not recorded after delivery');
        });
        DB::statement("alter table payments add constraint payments_collection_alert_only_on_delivery check (collection_alerted_at is null or method = 'cash_on_delivery')");
    }

    public function down(): void
    {
        DB::statement('alter table payments drop constraint payments_collection_alert_only_on_delivery');
        Schema::table('payments', static fn (Blueprint $table) => $table->dropColumn('collection_alerted_at'));
        Schema::dropIfExists('payment_method_settings');
        DB::statement('alter table orders drop constraint orders_pay_on_delivery_never_test');
        Schema::table('orders', static fn (Blueprint $table) => $table->dropColumn('pay_on_delivery'));
    }

    /**
     * How an order is paid for joins what never changes once it is placed.
     */
    private function freezePayOnDelivery(): void
    {
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
            SQL);
    }
};
