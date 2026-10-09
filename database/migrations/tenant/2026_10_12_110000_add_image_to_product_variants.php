<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A variant's own image is one of its product's gallery images, never a
 * copy. Deleting that image leaves the variant without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('image_media_id');
        });
    }
};
