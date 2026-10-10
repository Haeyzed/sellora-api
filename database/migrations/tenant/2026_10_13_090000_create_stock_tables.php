<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Stock, in whole units, per variant per location (section 13).
 *
 * Core has exactly one location per store, created here as the default, so
 * every existing and new store has it; the MultiLocation module adds more
 * without changing these tables. inventory_items holds each variant's stock
 * settings. stock_items holds the levels: on hand (physically there) and
 * reserved (held for checkouts and orders), never below zero. Every change
 * is a row in stock_movements, which a trigger makes append-only: updates and
 * deletes are refused, so the ledger is the trusted history of every level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('create unique index stock_locations_one_default on stock_locations (is_default) where is_default');
        DB::statement('alter table stock_locations add constraint stock_locations_default_is_active check (not is_default or is_active)');

        Schema::create('inventory_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained('product_variants')->restrictOnDelete();
            $table->boolean('tracks_stock')->default(true);
            $table->boolean('allows_backorder')->default(false);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestampsTz();
        });
        DB::statement('alter table inventory_items add constraint inventory_items_threshold_not_negative check (low_stock_threshold is null or low_stock_threshold >= 0)');

        Schema::create('stock_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->bigInteger('on_hand')->default(0);
            $table->bigInteger('reserved')->default(0);
            $table->timestampsTz();
            // Also the order rows are locked in: location, then variant.
            $table->unique(['stock_location_id', 'product_variant_id']);
            $table->index('product_variant_id');
        });
        DB::statement('alter table stock_items add constraint stock_items_not_negative check (on_hand >= 0 and reserved >= 0)');

        Schema::create('stock_movements', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('type', 24);
            $table->string('reason', 32)->nullable();
            $table->text('note')->nullable();
            $table->bigInteger('on_hand_delta');
            $table->bigInteger('reserved_delta');
            $table->bigInteger('on_hand_after');
            $table->bigInteger('reserved_after');
            $table->string('causer_type', 64)->nullable();
            $table->string('causer_id', 64)->nullable();
            $table->string('source_type', 64)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['stock_item_id', 'id']);
            $table->index(['product_variant_id', 'id']);
            $table->index(['source_type', 'source_id']);
        });
        DB::statement("alter table stock_movements add constraint stock_movements_known_type check (type in ('received', 'adjusted'))");
        DB::statement('alter table stock_movements add constraint stock_movements_changes_something check (on_hand_delta <> 0 or reserved_delta <> 0)');
        DB::statement('alter table stock_movements add constraint stock_movements_after_not_negative check (on_hand_after >= 0 and reserved_after >= 0)');
        DB::statement('alter table stock_movements add constraint stock_movements_causer_complete check ((causer_type is null and causer_id is null) or (causer_type is not null and causer_id is not null))');
        DB::statement('alter table stock_movements add constraint stock_movements_source_complete check ((source_type is null and source_id is null) or (source_type is not null and source_id is not null))');

        DB::unprepared(<<<'SQL'
            create function stock_movements_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'Stock movements are append-only; % is refused.', tg_op;
            end;
            $$;

            create trigger stock_movements_append_only before update or delete on stock_movements
                for each row execute function stock_movements_append_only();
            SQL);

        DB::table('stock_locations')->insert([
            'public_id' => strtolower((string) Str::ulid()),
            'name' => 'Main location',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists stock_movements_append_only on stock_movements; drop function if exists stock_movements_append_only();');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('stock_locations');
    }
};
