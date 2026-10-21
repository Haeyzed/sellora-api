<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What a refund does to the sale (Step 15b): it reduces the sale (returned
 * items, shipping, a price reduction) or returns money that was never part of
 * it (an overpayment, a payment in review, a payment for an order cancelled
 * before it was paid in full). Only a sale reduction gets a credit note.
 *
 * Refunds never change, so the trigger is set aside for this one backfill:
 * refunds drawn on a payment in review, or on a payment that succeeded after
 * its order was cancelled, return money; every other refund reduced the sale
 * (an overpayment given back before purposes existed can't be told apart,
 * and counts as a reduction). New refunds always say which they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', static function (Blueprint $table): void {
            $table->string('purpose', 16)->default('reduces_sale')->comment('reduces_sale or returns_payment: only a sale reduction gets a credit note');
        });

        DB::unprepared(<<<'SQL'
            alter table refunds disable trigger refunds_never_change;

            update refunds set purpose = 'returns_payment'
            where exists (
                select 1 from payment_refunds
                join payments on payments.id = payment_refunds.payment_id
                join orders on orders.id = refunds.order_id
                where payment_refunds.refund_id = refunds.id
                    and (payments.status = 'needs_review' or payments.succeeded_at > orders.cancelled_at)
            );

            alter table refunds enable trigger refunds_never_change;
            alter table refunds alter column purpose drop default;
            alter table refunds add constraint refunds_purpose_check check (purpose in ('reduces_sale', 'returns_payment'));
            SQL);
    }

    public function down(): void
    {
        Schema::table('refunds', static function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });
    }
};
