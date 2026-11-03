<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The daily invoice check waits out orders whose money moved moments ago, as a
 * credit note may still be on its way. updated_at can't say when that was: any
 * change sets it. Existing orders take the latest time their own records show
 * money moving; those with none stay empty, as nothing has moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->timestampTz('money_changed_at')->nullable()->after('paid_at')->comment('When its money last changed: a payment, a refund, a hold settled or given back, or its payment status; a retry that moves nothing leaves it. Null until money first moves');
        });

        DB::statement(<<<'SQL'
            update orders
            set money_changed_at = latest.changed_at
            from (
                select order_id, max(changed_at) as changed_at
                from (
                    select id as order_id, paid_at as changed_at from orders
                    union all
                    select order_id, greatest(succeeded_at, failed_at, needs_review_at) from payments
                    union all
                    select refunds.order_id, payment_refunds.succeeded_at from payment_refunds join refunds on refunds.id = payment_refunds.refund_id
                    union all
                    select order_id, greatest(committed_at, released_at, refunded_at) from checkout_payment_holds
                ) as moved
                where changed_at is not null
                group by order_id
            ) as latest
            where orders.id = latest.order_id
            SQL);
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropColumn('money_changed_at');
        });
    }
};
