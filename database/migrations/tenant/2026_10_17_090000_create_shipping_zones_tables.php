<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Shipping zones and the regions they cover (Step 13).
 *
 * A region is a country, or one of its states (codes from Geography, the
 * same source tax uses). A region belongs to at most one zone: the unique key
 * treats an empty state as one value, so two zones can never both cover a
 * whole country, or the same state. A state's zone beats its country's.
 * At most one zone covers "the rest of the world" (every country no zone
 * lists); it has no regions of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('covers_rest_of_world')->default(false);
            $table->timestampsTz();
        });
        DB::statement('create unique index shipping_zones_name_unique on shipping_zones (lower(name))');
        DB::statement('create unique index shipping_zones_one_rest_of_world on shipping_zones (covers_rest_of_world) where covers_rest_of_world');

        Schema::create('shipping_zone_regions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('state_code', 10)->nullable();
            $table->unique(['country_code', 'state_code'], 'shipping_zone_regions_one_zone_per_region')->nullsNotDistinct();
            $table->index('shipping_zone_id');
        });
        DB::statement("alter table shipping_zone_regions add constraint shipping_zone_regions_country_code check (country_code ~ '^[A-Z]{2}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_regions');
        Schema::dropIfExists('shipping_zones');
    }
};
