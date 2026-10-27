<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Returned shipments (Step 17): one of the store's own deliveries that left
 * with a driver and came back undelivered. Only an own-delivery shipment
 * can be returned, only once it has shipped, and returned is final. What it
 * held goes back on the shelf, held for the order again, so it can ship once
 * more or be given back when the order is cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', static function (Blueprint $table): void {
            $table->timestampTz('returned_at')->nullable()->after('delivered_at')->comment('When it came back to the store undelivered; own delivery only.');
        });

        DB::statement(<<<'SQL'
            alter table shipments
                drop constraint shipments_status_check,
                drop constraint shipments_shipped_at,
                add constraint shipments_status_check check (status in ('pending', 'ready_for_pickup', 'shipped', 'delivered', 'cancelled', 'returned')),
                add constraint shipments_shipped_at check ((shipped_at is not null) = (status in ('shipped', 'delivered', 'returned') and fulfilment <> 'pickup')),
                add constraint shipments_returned_at check ((returned_at is not null) = (status = 'returned')),
                add constraint shipments_returned_only_own_delivery check (status <> 'returned' or fulfilment = 'own_delivery')
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            alter table shipments
                drop constraint shipments_returned_only_own_delivery,
                drop constraint shipments_returned_at,
                drop constraint shipments_shipped_at,
                drop constraint shipments_status_check,
                add constraint shipments_status_check check (status in ('pending', 'ready_for_pickup', 'shipped', 'delivered', 'cancelled')),
                add constraint shipments_shipped_at check ((shipped_at is not null) = (status in ('shipped', 'delivered') and fulfilment <> 'pickup'))
            SQL);

        Schema::table('shipments', static function (Blueprint $table): void {
            $table->dropColumn('returned_at');
        });
    }
};
