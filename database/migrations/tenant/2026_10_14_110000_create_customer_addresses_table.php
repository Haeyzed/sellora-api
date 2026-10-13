<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A customer's saved addresses. Orders copy an address, never link one, so editing or deleting it never changes a past order.
        Schema::create('customer_addresses', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('recipient_name', 120);
            $table->string('company', 120)->nullable();
            $table->string('line1', 200);
            $table->string('line2', 200)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2);
            $table->string('phone', 16)->nullable();
            $table->timestampsTz();

            // For the default address links below, which must point at the customer's own address.
            $table->unique(['id', 'customer_id']);
        });

        DB::statement("alter table customer_addresses add constraint customer_addresses_phone_e164_check check (phone is null or phone ~ '^\\+[1-9][0-9]{6,14}$')");

        Schema::table('customers', static function (Blueprint $table): void {
            $table->unsignedBigInteger('default_shipping_address_id')->nullable()->after('locale');
            $table->unsignedBigInteger('default_billing_address_id')->nullable()->after('default_shipping_address_id');
        });

        // Deleting the address clears only the link, never the customer's own ID (Postgres 15 and later).
        foreach (['shipping', 'billing'] as $kind) {
            DB::statement("alter table customers add constraint customers_default_{$kind}_address_foreign
                foreign key (default_{$kind}_address_id, id) references customer_addresses (id, customer_id)
                on delete set null (default_{$kind}_address_id)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['shipping', 'billing'] as $kind) {
            DB::statement("alter table customers drop constraint if exists customers_default_{$kind}_address_foreign");
        }

        Schema::table('customers', static function (Blueprint $table): void {
            $table->dropColumn(['default_shipping_address_id', 'default_billing_address_id']);
        });

        Schema::dropIfExists('customer_addresses');
    }
};
