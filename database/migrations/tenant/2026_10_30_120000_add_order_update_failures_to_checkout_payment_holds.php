<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Once a hold is released or given back, its order's payments are recorded
 * again (its payment status, the refund it owes, a credit note). That can
 * fail on its own, after the hold has settled for good (its payment method
 * is called only once), so the failure is recorded on the holds it followed,
 * whatever their status, for staff and the hourly retry, with how many tries
 * in a row failed. A retry that works clears the failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->timestampTz('order_update_failed_at')->nullable()->after('settle_recovered_at')->comment('When recording its order\'s payments after it settled last failed; cleared once that works');
            $table->string('order_update_failure', 64)->nullable()->after('order_update_failed_at')->comment('Why recording its order\'s payments last failed, as a code');
            $table->unsignedInteger('order_update_failures')->default(0)->after('order_update_failure')->comment('How many tries to record its order\'s payments after it settled failed in a row');
        });

        DB::statement('alter table checkout_payment_holds add constraint checkout_payment_holds_order_update_failure_complete check ((order_update_failed_at is null) = (order_update_failure is null))');
    }

    public function down(): void
    {
        DB::statement('alter table checkout_payment_holds drop constraint checkout_payment_holds_order_update_failure_complete');

        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->dropColumn(['order_update_failed_at', 'order_update_failure', 'order_update_failures']);
        });
    }
};
