<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Where a driver was during a delivery (Step 17): personal data. Points are
 * taken only while the delivery is picked up or on the way, only from its own
 * driver. Staff see the trail; the order's customer (or guest) sees only the
 * latest point, only while it is on the way. Purged a retention period after
 * the delivery ends; deleted when the driver is erased.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_locations', static function (Blueprint $table): void {
            $table->comment('Where a driver was while a delivery was picked up or on the way; personal data, purged after the delivery ends.');
            $table->id();
            $table->foreignId('delivery_id')->comment('The delivery under way when the point was taken.')->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('driver_id')->comment('The driver whose position it is: always the delivery\'s driver.')->constrained('drivers')->restrictOnDelete();
            $table->decimal('latitude', 9, 6)->comment('Degrees, -90 to 90 (WGS 84).');
            $table->decimal('longitude', 9, 6)->comment('Degrees, -180 to 180 (WGS 84).');
            $table->integer('accuracy_meters')->nullable()->comment('How far off the point may be, in metres, as the phone reported it.');
            $table->timestampTz('created_at')->comment('When the point was received.');
            $table->index(['delivery_id', 'id']);
            $table->index('driver_id');
        });

        DB::statement(<<<'SQL'
            alter table driver_locations
                add constraint driver_locations_latitude_range check (latitude between -90 and 90),
                add constraint driver_locations_longitude_range check (longitude between -180 and 180),
                add constraint driver_locations_accuracy_not_negative check (accuracy_meters is null or accuracy_meters >= 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_locations');
    }
};
