<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Payments (Step 15.2). A payment is a financial record: what was expected,
 * what was received, how and when. Its order, method, gateway, mode, currency,
 * expected amount and reference never change; what was received is set once;
 * its status only moves forward:
 *
 *   pending     → succeeded, failed, needs_review, unresolved, cancelled
 *   unresolved  → succeeded, failed, needs_review, cancelled
 *   cancelled   → succeeded, needs_review   (money that arrived anyway)
 *   failed      → needs_review              (the gateway took money after saying it failed)
 *
 * "unresolved" means the gateway still had no final answer when checking
 * stopped (config payments.checks.give_up_after_hours); staff are told and
 * can ask again. "needs_review" means money arrived that doesn't match what
 * was expected (amount, currency, or test against live): it never counts
 * toward the order, and staff resolve it only by refunding what was received.
 *
 * Orders gain the test flag (set at placement, never changed) and the
 * payment statuses money can reach: partially paid, partially refunded and
 * refunded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->extendOrders();
        $this->createPayments();
        $this->createPaymentEvents();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        DB::unprepared('drop function if exists payments_are_financial_records() cascade; drop function if exists payment_records_never_change() cascade;');
    }

    private function extendOrders(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->comment('Placed with a test-mode payment method: only test payments pay it');
        });

        DB::statement(<<<'SQL'
            alter table orders
                drop constraint orders_payment_status_check,
                drop constraint orders_paid_when_paid,
                drop constraint orders_completed_when_done,
                drop constraint orders_refund_due_only_when_paid_and_cancelled
            SQL);
        DB::statement(<<<'SQL'
            alter table orders
                add constraint orders_payment_status_check check (payment_status in ('unpaid', 'partially_paid', 'paid', 'partially_refunded', 'refunded')),
                add constraint orders_paid_when_paid check (
                    (payment_status <> 'paid' or paid_at is not null) and (payment_status not in ('unpaid', 'partially_paid') or paid_at is null)
                ),
                add constraint orders_completed_when_done check (
                    (status = 'completed') = (completed_at is not null)
                    and (status <> 'completed' or (payment_status in ('paid', 'partially_refunded', 'refunded') and fulfilment_status in ('fulfilled', 'not_required')))
                ),
                add constraint orders_refund_due_only_when_money_and_cancelled check (
                    not refund_due or (payment_status in ('partially_paid', 'paid', 'partially_refunded') and status = 'cancelled')
                )
            SQL);

        DB::statement('drop index orders_awaiting_payment');
        DB::statement("create index orders_awaiting_payment on orders (payment_due_at) where status = 'open' and payment_status in ('unpaid', 'partially_paid')");

        // The test flag joins what never changes once an order is placed.
        DB::unprepared(<<<'SQL'
            create or replace function orders_are_financial_records() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Orders are financial records; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if row(new.id, new.public_id, new.number, new.checkout_id, new.locale, new.currency, new.base_currency, new.exchange_rate, new.market_rate,
                        new.margin_percent, new.rate_source, new.rate_as_of, new.tax_mode, new.tax_calculated_by, new.tax_country_code, new.tax_state_code,
                        new.tax_postal_code, new.subtotal_amount, new.discount_amount, new.shipping_amount, new.shipping_discount_amount, new.tax_amount,
                        new.total_amount, new.base_total_amount, new.placed_at, new.payment_due_at, new.created_at, new.is_test)
                    is distinct from row(old.id, old.public_id, old.number, old.checkout_id, old.locale, old.currency, old.base_currency, old.exchange_rate,
                        old.market_rate, old.margin_percent, old.rate_source, old.rate_as_of, old.tax_mode, old.tax_calculated_by, old.tax_country_code,
                        old.tax_state_code, old.tax_postal_code, old.subtotal_amount, old.discount_amount, old.shipping_amount, old.shipping_discount_amount,
                        old.tax_amount, old.total_amount, old.base_total_amount, old.placed_at, old.payment_due_at, old.created_at, old.is_test) then
                    raise exception 'An order''s number, amounts, rates, placement and test flag never change.' using errcode = 'check_violation';
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

    private function createPayments(): void
    {
        Schema::create('payments', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('method', 24);
            $table->string('gateway', 64)->nullable();
            $table->foreignId('connection_id')->nullable()->constrained('payment_gateway_connections')->restrictOnDelete();
            $table->boolean('is_test');
            $table->string('status', 16)->default('pending');
            $table->char('currency', 3);
            $table->bigInteger('amount_amount')->comment('What was expected, in minor units');
            $table->char('received_currency', 3)->nullable();
            $table->bigInteger('received_amount')->nullable()->comment('What arrived, in minor units; set once');
            $table->string('reference', 64)->unique()->comment('Ours: the gateway reference and idempotency key');
            $table->string('idempotency_key', 128)->nullable()->comment('The client\'s, so a retry returns this payment');
            $table->string('gateway_reference', 128)->nullable();
            $table->string('redirect_url', 2048)->nullable()->comment('The gateway\'s hosted page for this attempt');
            $table->string('failure_code', 64)->nullable();
            $table->string('review_reason', 32)->nullable();
            $table->string('card_brand', 32)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->string('confirmed_by_type', 64)->nullable();
            $table->string('confirmed_by_id', 64)->nullable();
            $table->timestampTz('succeeded_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('needs_review_at')->nullable();
            $table->timestampTz('unresolved_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->unsignedSmallInteger('checks')->default(0);
            $table->timestampsTz();
            $table->index(['order_id', 'id']);
            $table->index(['status', 'id']);
            $table->unique(['order_id', 'idempotency_key']);
        });

        DB::statement(<<<'SQL'
            alter table payments
                add constraint payments_method_check check (method in ('gateway', 'bank_transfer', 'cash_on_delivery')),
                add constraint payments_status_check check (status in ('pending', 'succeeded', 'failed', 'needs_review', 'unresolved', 'cancelled')),
                add constraint payments_gateway_only_for_gateway check ((method = 'gateway') = (gateway is not null and connection_id is not null)),
                add constraint payments_amount_positive check (amount_amount > 0),
                add constraint payments_received_complete check ((received_amount is null) = (received_currency is null)),
                add constraint payments_received_when_succeeded check (status <> 'succeeded' or (received_amount > 0 and received_currency = currency)),
                add constraint payments_succeeded_at check ((status = 'succeeded') = (succeeded_at is not null)),
                add constraint payments_failed_at check ((status = 'failed') = (failed_at is not null)),
                add constraint payments_needs_review_at check ((status = 'needs_review') = (needs_review_at is not null)),
                add constraint payments_card_last4_digits check (card_last4 is null or card_last4 ~ '^[0-9]{4}$'),
                add constraint payments_confirmed_by_complete check ((confirmed_by_type is null) = (confirmed_by_id is null))
            SQL);

        DB::unprepared(<<<'SQL'
            create function payments_are_financial_records() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Payments are financial records; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if row(new.id, new.public_id, new.order_id, new.method, new.gateway, new.connection_id, new.is_test, new.currency, new.amount_amount,
                        new.reference, new.idempotency_key, new.created_at)
                    is distinct from row(old.id, old.public_id, old.order_id, old.method, old.gateway, old.connection_id, old.is_test, old.currency,
                        old.amount_amount, old.reference, old.idempotency_key, old.created_at) then
                    raise exception 'A payment''s order, method, gateway, mode, expected amount and reference never change.' using errcode = 'check_violation';
                end if;

                if old.received_amount is not null and row(new.received_amount, new.received_currency) is distinct from row(old.received_amount, old.received_currency) then
                    raise exception 'What a payment received is recorded once and never changes.' using errcode = 'check_violation';
                end if;

                if new.status is distinct from old.status and not (
                    (old.status = 'pending' and new.status in ('succeeded', 'failed', 'needs_review', 'unresolved', 'cancelled'))
                    or (old.status = 'unresolved' and new.status in ('succeeded', 'failed', 'needs_review', 'cancelled'))
                    or (old.status = 'cancelled' and new.status in ('succeeded', 'needs_review'))
                    or (old.status = 'failed' and new.status = 'needs_review')
                ) then
                    raise exception 'A payment can''t go from % to %.', old.status, new.status using errcode = 'check_violation';
                end if;

                -- Card details are recorded once, and removed only by an erasure.
                if row(new.card_brand, new.card_last4) is distinct from row(old.card_brand, old.card_last4)
                    and not (old.card_brand is null and old.card_last4 is null)
                    and not (new.card_brand is null and new.card_last4 is null and coalesce(current_setting('app.erasing_personal_data', true), '') = 'on') then
                    raise exception 'A payment''s card details are recorded once and change only through erasure.' using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;

            create trigger payments_are_financial_records before update or delete on payments
                for each row execute function payments_are_financial_records();
            create trigger payments_are_never_emptied before truncate on payments
                for each statement execute function payments_are_financial_records();
            SQL);
    }

    private function createPaymentEvents(): void
    {
        Schema::create('payment_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('type', 40);
            $table->jsonb('data')->nullable();
            $table->string('actor_type', 64)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->timestampTz('created_at');
            $table->index(['payment_id', 'id']);
        });
        DB::statement('alter table payment_events add constraint payment_events_actor_complete check ((actor_type is null) = (actor_id is null))');

        DB::unprepared(<<<'SQL'
            create function payment_records_never_change() returns trigger language plpgsql as $$
            begin
                raise exception '% rows are financial records; % is refused.', tg_table_name, tg_op using errcode = 'check_violation';
            end;
            $$;

            create trigger payment_events_never_change before update or delete on payment_events
                for each row execute function payment_records_never_change();
            create trigger payment_events_never_emptied before truncate on payment_events
                for each statement execute function payment_records_never_change();
            SQL);
    }
};
