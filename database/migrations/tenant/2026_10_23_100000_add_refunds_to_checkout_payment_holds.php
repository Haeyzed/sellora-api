<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What a payment method committed for an order can be given back when the
 * order is cancelled (Step 16): the hold is then refunded, and stops counting
 * as paid. A give-back that failed is recorded on the hold, for staff and the
 * hourly retry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->timestampTz('refunded_at')->nullable()->after('released_at');
            $table->timestampTz('refund_failed_at')->nullable()->after('refunded_at')->comment('When giving it back last failed; cleared once it is given back');
            $table->string('refund_failure', 64)->nullable()->after('refund_failed_at')->comment('Why giving it back last failed, as a code');
        });

        DB::statement(<<<'SQL'
            alter table checkout_payment_holds
                drop constraint checkout_payment_holds_status_check,
                drop constraint checkout_payment_holds_committed_when_committed,
                add constraint checkout_payment_holds_status_check check (status in ('held', 'committed', 'released', 'refunded')),
                add constraint checkout_payment_holds_committed_when_committed check ((status in ('committed', 'refunded')) = (committed_at is not null)),
                add constraint checkout_payment_holds_refunded_when_refunded check ((status = 'refunded') = (refunded_at is not null)),
                add constraint checkout_payment_holds_refund_failure_complete check ((refund_failed_at is null) = (refund_failure is null)),
                add constraint checkout_payment_holds_refund_failure_only_while_committed check (refund_failed_at is null or status = 'committed')
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            alter table checkout_payment_holds
                drop constraint checkout_payment_holds_refund_failure_only_while_committed,
                drop constraint checkout_payment_holds_refund_failure_complete,
                drop constraint checkout_payment_holds_refunded_when_refunded,
                drop constraint checkout_payment_holds_committed_when_committed,
                drop constraint checkout_payment_holds_status_check,
                add constraint checkout_payment_holds_status_check check (status in ('held', 'committed', 'released')),
                add constraint checkout_payment_holds_committed_when_committed check ((status = 'committed') = (committed_at is not null))
            SQL);

        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->dropColumn(['refunded_at', 'refund_failed_at', 'refund_failure']);
        });
    }
};
