<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Committing or releasing a hold can fail when its payment method throws or
 * is no longer registered. The failure is recorded on the hold, for staff and
 * the hourly retry, with how many tries in a row failed and when staff were
 * alerted (once). A later retry that settles it clears the failure and
 * records when it recovered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->timestampTz('settle_failed_at')->nullable()->after('refund_failure')->comment('When committing or releasing it last failed; cleared once it is settled');
            $table->string('settle_failure', 64)->nullable()->after('settle_failed_at')->comment('Why committing or releasing it last failed, as a code');
            $table->unsignedInteger('settle_failures')->default(0)->after('settle_failure')->comment('How many tries to commit or release it failed in a row');
            $table->timestampTz('settle_alerted_at')->nullable()->after('settle_failures')->comment('When staff were alerted that it keeps failing; only once');
            $table->timestampTz('settle_recovered_at')->nullable()->after('settle_alerted_at')->comment('When a retry settled it after it had failed');
        });

        DB::statement(<<<'SQL'
            alter table checkout_payment_holds
                add constraint checkout_payment_holds_settle_failure_complete check ((settle_failed_at is null) = (settle_failure is null)),
                add constraint checkout_payment_holds_settle_failure_only_while_held check (settle_failed_at is null or status = 'held')
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            alter table checkout_payment_holds
                drop constraint checkout_payment_holds_settle_failure_only_while_held,
                drop constraint checkout_payment_holds_settle_failure_complete
            SQL);

        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->dropColumn(['settle_failed_at', 'settle_failure', 'settle_failures', 'settle_alerted_at', 'settle_recovered_at']);
        });
    }
};
