<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Shipments (Step 14e): what of a paid order is sent together, how, and
 * where it is. An order may ship in parts; a line's quantity across its
 * shipments that aren't cancelled never exceeds what was bought (checked
 * under the order's lock). A shipment that ships with a carrier or the
 * store's own drivers goes pending → shipped → delivered; a pickup goes
 * pending → ready_for_pickup → delivered (collected). A shipment that hasn't
 * left can be cancelled. Its stock leaves the shelf when it ships, or when a
 * pickup is collected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('fulfilment', 16);
            $table->foreignId('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('carrier', 64)->nullable();
            $table->string('carrier_service', 64)->nullable();
            $table->string('tracking_number', 128)->nullable();
            $table->string('tracking_url', 2048)->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('created_by_type', 64);
            $table->string('created_by_id', 64);
            $table->timestampsTz();
            $table->index(['order_id', 'id']);
            $table->index(['status', 'id']);
        });
        DB::statement(<<<'SQL'
            alter table shipments
                add constraint shipments_fulfilment_check check (fulfilment in ('ship', 'own_delivery', 'pickup')),
                add constraint shipments_status_check check (status in ('pending', 'ready_for_pickup', 'shipped', 'delivered', 'cancelled')),
                add constraint shipments_ready_only_for_pickup check (status <> 'ready_for_pickup' or fulfilment = 'pickup'),
                add constraint shipments_pickups_never_ship check (status <> 'shipped' or fulfilment <> 'pickup'),
                add constraint shipments_tracking_url_https check (tracking_url is null or tracking_url like 'https://%'),
                add constraint shipments_shipped_at check ((shipped_at is not null) = (status in ('shipped', 'delivered') and fulfilment <> 'pickup')),
                add constraint shipments_delivered_at check ((delivered_at is not null) = (status = 'delivered')),
                add constraint shipments_cancelled_at check ((cancelled_at is not null) = (status = 'cancelled'))
            SQL);

        Schema::create('shipment_lines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('order_line_id')->constrained('order_lines')->restrictOnDelete();
            $table->integer('quantity');
            $table->unique(['shipment_id', 'order_line_id']);
            $table->index('order_line_id');
        });
        DB::statement('alter table shipment_lines add constraint shipment_lines_quantity_positive check (quantity > 0)');
        // What a shipment holds is fixed once it exists; cancelling it is how a mistake is undone.
        DB::unprepared('create trigger shipment_lines_never_change before update or delete on shipment_lines for each row execute function order_records_never_change();');
        DB::unprepared('create trigger shipment_lines_never_emptied before truncate on shipment_lines for each statement execute function order_records_never_change();');
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_lines');
        Schema::dropIfExists('shipments');
    }
};
