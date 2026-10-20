<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A gateway refund still pending after config
 * payments.checks.refund_daily_after_days is asked about once a day, and
 * staff are alerted once: slow_alerted_at records that they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->timestampTz('slow_alerted_at')->nullable()->comment('When staff were told it was still pending at the gateway');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->dropColumn('slow_alerted_at');
        });
    }
};
