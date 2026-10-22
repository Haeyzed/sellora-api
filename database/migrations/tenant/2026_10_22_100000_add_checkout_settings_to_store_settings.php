<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * How the store checks out (Step 16): whether guests may buy without an
 * account (on, for every store, existing ones included), and whether buyers
 * must give a tax ID (optional unless the store requires one, for example a
 * store selling to businesses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->boolean('guest_checkout')->default(true)->comment('Whether guests may buy without an account');
            $table->string('buyer_tax_id_requirement', 16)->default('optional')->comment('Whether buyers must give a tax ID at checkout');
        });

        DB::statement("alter table store_settings add constraint store_settings_buyer_tax_id_requirement_check check (buyer_tax_id_requirement in ('optional', 'required'))");
    }

    public function down(): void
    {
        DB::statement('alter table store_settings drop constraint store_settings_buyer_tax_id_requirement_check');

        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->dropColumn(['guest_checkout', 'buyer_tax_id_requirement']);
        });
    }
};
