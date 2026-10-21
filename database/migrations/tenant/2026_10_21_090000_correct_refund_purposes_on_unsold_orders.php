<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Corrects the refund-purpose backfill (2026_10_20_090000) to match the live
 * rule: a refund on an order never sold (no "paid" in its history, such as one
 * paid in part and then cancelled) returns money, so it has no invoice to
 * credit. That backfill recorded such refunds as reducing the sale. New
 * refunds never do, so this only changes rows it filled in.
 *
 * Refunds never change, so the trigger is set aside for this correction.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table refunds disable trigger refunds_never_change;

            update refunds set purpose = 'returns_payment'
            where purpose = 'reduces_sale'
            and not exists (
                select 1 from order_events
                where order_events.order_id = refunds.order_id and order_events.type = 'paid'
            );

            alter table refunds enable trigger refunds_never_change;
            SQL);
    }

    /**
     * Nothing to undo: the rows it corrected were wrong.
     */
    public function down(): void {}
};
