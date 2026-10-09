<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Which variants went to the trash because their product did, so restoring
 * the product brings back exactly those and not variants trashed on their
 * own before (timestamps can't tell them apart reliably). Only a variant in
 * the trash can carry the mark.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->boolean('trashed_with_product')->default(false);
        });

        DB::statement('alter table product_variants add constraint product_variants_trashed_with_product_in_trash check (not trashed_with_product or deleted_at is not null)');
    }

    public function down(): void
    {
        Schema::table('product_variants', static function (Blueprint $table): void {
            $table->dropColumn('trashed_with_product');
        });
    }
};
