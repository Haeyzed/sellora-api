<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A refund that went back on an order with no invoice to credit (a hold
 * released left it short of its total, so it was never invoiced) needs no
 * credit note. The hourly sweep records that once, so it never picks the
 * refund up again or lets a backlog of them hold back real credit notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->timestampTz('no_credit_note_at')->nullable()->after('succeeded_at')->comment('When the hourly sweep found its order had no invoice to credit, so it needs no credit note and is never picked up again');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->dropColumn('no_credit_note_at');
        });
    }
};
