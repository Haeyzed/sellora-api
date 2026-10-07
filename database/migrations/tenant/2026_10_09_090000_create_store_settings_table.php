<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Section 10: a store's core settings live in one typed, audited row. The
 * check constraints keep that row the only one, keep the default language
 * among the enabled ones, keep the tax mode and display units to known
 * values, and stop an address state without its country. Countries,
 * currencies and states are ISO codes (section 2.1).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_settings', static function (Blueprint $table): void {
            $table->smallInteger('id')->primary()->default(1);
            $table->string('name', 120);
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->string('timezone', 64);
            $table->string('default_locale', 12);
            $table->jsonb('enabled_locales');
            $table->string('tax_mode', 16);
            $table->string('weight_unit', 8);
            $table->string('dimension_unit', 8);
            $table->string('contact_email', 254)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('address_city', 120)->nullable();
            $table->string('address_state_code', 10)->nullable();
            $table->string('address_postal_code', 20)->nullable();
            $table->char('address_country_code', 2)->nullable();
            $table->timestampsTz();
        });

        DB::statement('alter table store_settings add constraint store_settings_single_row check (id = 1)');
        DB::statement("alter table store_settings add constraint store_settings_default_locale_enabled check (jsonb_typeof(enabled_locales) = 'array' and enabled_locales @> jsonb_build_array(default_locale))");
        DB::statement("alter table store_settings add constraint store_settings_known_tax_mode check (tax_mode in ('inclusive', 'exclusive'))");
        DB::statement("alter table store_settings add constraint store_settings_known_units check (weight_unit in ('kg', 'g', 'lb', 'oz') and dimension_unit in ('cm', 'mm', 'm', 'in'))");
        DB::statement('alter table store_settings add constraint store_settings_state_needs_country check (address_state_code is null or address_country_code is not null)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
