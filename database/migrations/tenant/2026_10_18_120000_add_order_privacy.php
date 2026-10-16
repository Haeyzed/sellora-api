<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Orders and personal data (Step 14f). Orders are financial records, so they
 * are anonymised rather than deleted: the buyer's contact details, note and
 * addresses go (the country and state stay), the amounts and tax stay. That
 * happens when the buyer's data is erased, once the order is settled (an
 * order still being paid for is cancelled first; one paid for and still
 * being fulfilled waits until it is settled), and for every settled order
 * once the store's retention period for orders has passed: by its country
 * (config/geography.php), unless the store set its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->string('contact_email', 254)->nullable()->comment('In lower case, as given at checkout; null once anonymised')->change();
            $table->boolean('anonymise_when_settled')->default(false)->comment('The buyer\'s data was erased while it was still being fulfilled');
            $table->timestampTz('anonymised_at')->nullable();
            $table->index('anonymise_when_settled');
        });
        DB::statement('alter table orders add constraint orders_email_until_anonymised check (contact_email is not null or anonymised_at is not null)');
        // An order still waiting for payment is cancelled when its buyer's data is erased.
        DB::statement('alter table orders drop constraint orders_cancellation_reason_check');
        DB::statement("alter table orders add constraint orders_cancellation_reason_check check (cancellation_reason in ('staff', 'customer', 'payment_expired', 'data_erased'))");

        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->smallInteger('order_retention_years')->nullable()->comment('How long settled orders keep personal data; null for the store country\'s default');
        });
        DB::statement('alter table store_settings add constraint store_settings_order_retention_years check (order_retention_years is null or order_retention_years between 3 and 30)');
    }

    public function down(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->dropColumn('order_retention_years');
        });
        DB::statement('alter table orders drop constraint orders_cancellation_reason_check');
        DB::statement("alter table orders add constraint orders_cancellation_reason_check check (cancellation_reason in ('staff', 'customer', 'payment_expired'))");
        DB::statement('alter table orders drop constraint orders_email_until_anonymised');
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropColumn(['anonymise_when_settled', 'anonymised_at']);
        });
    }
};
