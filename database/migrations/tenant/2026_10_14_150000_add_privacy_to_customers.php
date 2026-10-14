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
        // An erased customer's row stays, so past orders still point at something, with every personal field cleared.
        Schema::table('customers', static function (Blueprint $table): void {
            $table->string('name', 120)->nullable()->change();
            $table->string('email', 254)->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->timestampTz('erased_at')->nullable();
        });

        // NULL-safe: every test is IS NULL, IS NOT NULL or a comparison on a NOT NULL column, so no part can be NULL and pass by accident.
        DB::statement(<<<'SQL'
            alter table customers add constraint customers_erasure_check check (
                (erased_at is null and name is not null and email is not null and password is not null)
                or (
                    erased_at is not null
                    and name is null and email is null and password is null and phone is null and locale is null
                    and tax_id is null and tax_id_country is null and is_tax_exempt = false and is_active = false
                    and search_text = '' and default_shipping_address_id is null and default_billing_address_id is null
                )
            )
            SQL);

        // A customer's own export of their personal data, built in the background and downloadable for a while.
        Schema::create('customer_data_exports', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('status', 20);
            $table->string('disk', 40);
            $table->string('path')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();

            $table->index(['customer_id', 'id']);
        });

        DB::statement("alter table customer_data_exports add constraint customer_data_exports_status_check check (status in ('pending', 'ready', 'failed'))");
        DB::statement("alter table customer_data_exports add constraint customer_data_exports_ready_check check (status <> 'ready' or (path is not null and expires_at is not null))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_data_exports');
        DB::statement('alter table customers drop constraint if exists customers_erasure_check');

        Schema::table('customers', static function (Blueprint $table): void {
            $table->dropColumn('erased_at');
        });
    }
};
