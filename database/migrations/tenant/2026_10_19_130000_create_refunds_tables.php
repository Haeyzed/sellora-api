<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Refunds (Step 15.5). A refund is what staff gave back on an order: an
 * amount, or lines and shipping at what the buyer paid for them, tax
 * included. It draws on one or more of the order's payments, each part
 * going back the way that payment came (payment_refunds): through its
 * gateway, or recorded as paid back by hand.
 *
 * A refund and its lines are written once and never change. A part drawn
 * from a payment only moves forward:
 *
 *   pending → succeeded, failed
 *   failed  → succeeded   (the gateway refunded after saying it failed)
 *
 * What a payment can still give back is what it received less its parts
 * pending or succeeded, read under the order's lock and then the payment's.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createRefunds();
        $this->createRefundLines();
        $this->createPaymentRefunds();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('refund_lines');
        Schema::dropIfExists('refunds');
        DB::unprepared('drop function if exists payment_refunds_only_move_forward() cascade;');
    }

    private function createRefunds(): void
    {
        Schema::create('refunds', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('amount_amount')->comment('All of it, in minor units: lines, shipping or an amount');
            $table->bigInteger('shipping_amount')->default(0)->comment('The part for shipping, its tax included');
            $table->bigInteger('shipping_tax_amount')->default(0);
            $table->string('reason', 500)->nullable();
            $table->string('idempotency_key', 128)->nullable()->comment('The client\'s, so a retry returns this refund');
            $table->string('requested_by_type', 64);
            $table->string('requested_by_id', 64);
            $table->timestampTz('created_at');
            $table->index(['order_id', 'id']);
            $table->unique(['order_id', 'idempotency_key']);
        });

        DB::statement(<<<'SQL'
            alter table refunds
                add constraint refunds_amount_positive check (amount_amount > 0),
                add constraint refunds_shipping_within check (shipping_amount >= 0 and shipping_tax_amount >= 0 and shipping_tax_amount <= shipping_amount and shipping_amount <= amount_amount)
            SQL);
        DB::unprepared(<<<'SQL'
            create trigger refunds_never_change before update or delete on refunds
                for each row execute function payment_records_never_change();
            create trigger refunds_never_emptied before truncate on refunds
                for each statement execute function payment_records_never_change();
            SQL);
    }

    private function createRefundLines(): void
    {
        Schema::create('refund_lines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->foreignId('order_line_id')->constrained('order_lines')->restrictOnDelete();
            $table->integer('quantity');
            $table->char('currency', 3);
            $table->bigInteger('amount_amount')->comment('What the buyer paid for these units, tax included');
            $table->bigInteger('tax_amount');
            $table->timestampTz('created_at');
            $table->unique(['refund_id', 'order_line_id']);
            $table->index('order_line_id');
        });

        DB::statement(<<<'SQL'
            alter table refund_lines
                add constraint refund_lines_quantity_positive check (quantity > 0),
                add constraint refund_lines_amounts check (amount_amount >= 0 and tax_amount >= 0 and tax_amount <= amount_amount)
            SQL);
        DB::unprepared(<<<'SQL'
            create trigger refund_lines_never_change before update or delete on refund_lines
                for each row execute function payment_records_never_change();
            create trigger refund_lines_never_emptied before truncate on refund_lines
                for each statement execute function payment_records_never_change();
            SQL);
    }

    private function createPaymentRefunds(): void
    {
        Schema::create('payment_refunds', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->char('currency', 3);
            $table->bigInteger('amount_amount')->comment('What goes back from this payment, in minor units');
            $table->string('reference', 64)->unique()->comment('Ours: the gateway\'s idempotency key for it');
            $table->string('gateway_reference', 128)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('recorded_by_type', 64)->nullable()->comment('Who recorded paying it back by hand');
            $table->string('recorded_by_id', 64)->nullable();
            $table->timestampTz('succeeded_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->unsignedSmallInteger('checks')->default(0);
            $table->timestampsTz();
            $table->index(['payment_id', 'status']);
            $table->index(['status', 'id']);
            $table->unique(['refund_id', 'payment_id']);
        });

        DB::statement(<<<'SQL'
            alter table payment_refunds
                add constraint payment_refunds_status_check check (status in ('pending', 'succeeded', 'failed')),
                add constraint payment_refunds_amount_positive check (amount_amount > 0),
                add constraint payment_refunds_succeeded_at check ((status = 'succeeded') = (succeeded_at is not null)),
                add constraint payment_refunds_failed_at check ((status = 'failed') = (failed_at is not null)),
                add constraint payment_refunds_recorded_by_complete check ((recorded_by_type is null) = (recorded_by_id is null))
            SQL);

        DB::unprepared(<<<'SQL'
            create function payment_refunds_only_move_forward() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Refunds are financial records; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if row(new.id, new.public_id, new.refund_id, new.payment_id, new.currency, new.amount_amount, new.reference, new.recorded_by_type, new.recorded_by_id, new.created_at)
                    is distinct from row(old.id, old.public_id, old.refund_id, old.payment_id, old.currency, old.amount_amount, old.reference, old.recorded_by_type, old.recorded_by_id, old.created_at) then
                    raise exception 'What a refund gives back, from which payment and who recorded it never change.' using errcode = 'check_violation';
                end if;

                if new.status is distinct from old.status and not (
                    (old.status = 'pending' and new.status in ('succeeded', 'failed'))
                    or (old.status = 'failed' and new.status = 'succeeded')
                ) then
                    raise exception 'A refund can''t go from % to %.', old.status, new.status using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;

            create trigger payment_refunds_only_move_forward before update or delete on payment_refunds
                for each row execute function payment_refunds_only_move_forward();
            create trigger payment_refunds_are_never_emptied before truncate on payment_refunds
                for each statement execute function payment_refunds_only_move_forward();
            SQL);
    }
};
