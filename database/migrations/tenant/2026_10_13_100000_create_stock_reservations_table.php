<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Stock held for checkouts and orders (section 13). A holder (a checkout or
 * an order, by morph name and public ID) has at most one active reservation
 * per variant and location; a partial unique index makes reserving again
 * change it instead of adding another. Only active reservations hold stock;
 * the others are settled for good. Part of a reservation may be
 * backordered: reserved beyond what was available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('holder_type', 64);
            $table->string('holder_id', 64);
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('backordered_quantity')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();
            $table->index(['holder_type', 'holder_id']);
            $table->index(['stock_location_id', 'product_variant_id', 'status']);
        });

        DB::statement("create unique index stock_reservations_one_active_per_holder on stock_reservations (holder_type, holder_id, stock_location_id, product_variant_id) where status = 'active'");
        DB::statement("create index stock_reservations_expiring on stock_reservations (expires_at) where status = 'active' and expires_at is not null");
        DB::statement("alter table stock_reservations add constraint stock_reservations_known_status check (status in ('active', 'committed', 'released', 'expired'))");
        DB::statement('alter table stock_reservations add constraint stock_reservations_quantity_positive check (quantity > 0)');
        DB::statement('alter table stock_reservations add constraint stock_reservations_backorder_within_quantity check (backordered_quantity >= 0 and backordered_quantity <= quantity)');
        DB::statement("alter table stock_reservations add constraint stock_reservations_settled_when_not_active check ((status = 'active' and settled_at is null) or (status <> 'active' and settled_at is not null))");

        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted', 'reserved', 'released', 'committed', 'expired'))");
    }

    public function down(): void
    {
        DB::statement('alter table stock_movements drop constraint stock_movements_known_type');
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted'))");
        Schema::dropIfExists('stock_reservations');
    }
};
