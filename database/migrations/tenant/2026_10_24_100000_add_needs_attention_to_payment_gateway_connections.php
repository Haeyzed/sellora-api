<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Step 16b. A gateway that refuses the store's keys (revoked or rotated on
 * the gateway's side) never fails a payment or a refund: the connection is
 * marked as needing the merchant to reconnect, and the mark is cleared by
 * reconnecting or by a check that finds the keys work again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_connections', static function (Blueprint $table): void {
            $table->timestampTz('needs_attention_at')->nullable()->comment('When the gateway first refused its keys, since they last worked');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_connections', static function (Blueprint $table): void {
            $table->dropColumn('needs_attention_at');
        });
    }
};
