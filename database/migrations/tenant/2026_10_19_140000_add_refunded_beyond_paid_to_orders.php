<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An order refunded beyond what was paid (Step 15.5). When a gateway reports
 * a refund as failed and later as succeeded, the money really went back; if
 * staff refunded again after the failure, the order's refunds now exceed its
 * payments. The order keeps the gateway's word and is flagged for staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->boolean('refunded_beyond_paid')->default(false)->comment('More was refunded than was paid: staff should look at it');
        });
    }

    public function down(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropColumn('refunded_beyond_paid');
        });
    }
};
