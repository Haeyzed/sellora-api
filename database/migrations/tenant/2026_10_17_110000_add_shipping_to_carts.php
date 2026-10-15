<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Shipping in the cart (Step 13): the state the shopper is in, for a
 * shipping and tax estimate by state, and the shipping method they chose.
 * A code only, never a street address: the full address comes at checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', static function (Blueprint $table): void {
            $table->string('destination_state_code', 10)->nullable()->after('destination_country_code');
            $table->foreignId('shipping_method_id')->nullable()->after('promotion_code')->constrained('shipping_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carts', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('destination_state_code');
        });
    }
};
