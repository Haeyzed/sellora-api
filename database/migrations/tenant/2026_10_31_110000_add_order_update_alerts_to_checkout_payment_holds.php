<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Recording an order's payments after its holds settled can keep failing.
 * Staff are alerted once when it does, and this keeps that to once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->timestampTz('order_update_alerted_at')->nullable()->after('order_update_failures')->comment('When staff were alerted that recording its order\'s payments keeps failing; only once');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_payment_holds', static function (Blueprint $table): void {
            $table->dropColumn('order_update_alerted_at');
        });
    }
};
