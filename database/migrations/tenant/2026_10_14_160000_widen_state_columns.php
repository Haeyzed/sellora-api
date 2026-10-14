<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * States now come only from commerceguys/addressing (section 3.3). A few of
 * its codes are names ("Boa Vista", up to 16 characters), and where it lists
 * no states for a country an address's state is free text (up to 100). Tax
 * rates only ever hold listed codes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->string('address_state_code', 100)->nullable()->change();
        });

        Schema::table('customer_addresses', static function (Blueprint $table): void {
            $table->string('state_code', 100)->nullable()->change();
        });

        Schema::table('tax_rates', static function (Blueprint $table): void {
            $table->string('state_code', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Fails if a longer state has been saved since.
     */
    public function down(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->string('address_state_code', 10)->nullable()->change();
        });

        Schema::table('customer_addresses', static function (Blueprint $table): void {
            $table->string('state_code', 10)->nullable()->change();
        });

        Schema::table('tax_rates', static function (Blueprint $table): void {
            $table->string('state_code', 10)->nullable()->change();
        });
    }
};
