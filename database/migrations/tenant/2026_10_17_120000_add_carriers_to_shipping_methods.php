<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Live carrier rates (Step 13): a shipped method can be priced by a
 * carrier's service, through an integration, instead of by brackets. It
 * names the carrier and the service, and may have a backup price (in the
 * base currency, with optional exact amounts per currency) used when the
 * carrier can't quote; without one, it isn't offered then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', static function (Blueprint $table): void {
            $table->string('carrier', 50)->nullable()->after('pricing');
            $table->string('carrier_service', 100)->nullable()->after('carrier');
            $table->bigInteger('backup_amount')->nullable()->after('free_over_amount');
        });
        DB::statement(<<<'SQL'
            alter table shipping_methods
                drop constraint shipping_methods_pricing,
                add constraint shipping_methods_pricing check (pricing in ('flat', 'weight', 'order_value', 'carrier')),
                add constraint shipping_methods_carrier check (
                    (pricing = 'carrier') = (carrier is not null and carrier_service is not null)
                    and (pricing <> 'carrier' or fulfilment = 'ship')
                ),
                add constraint shipping_methods_backup check (backup_amount is null or (pricing = 'carrier' and backup_amount >= 0))
            SQL);

        Schema::table('shipping_method_amounts', static function (Blueprint $table): void {
            $table->bigInteger('free_over_amount')->nullable()->change();
            $table->bigInteger('backup_amount')->nullable();
        });
        DB::statement(<<<'SQL'
            alter table shipping_method_amounts
                drop constraint shipping_method_amounts_free_over_positive,
                add constraint shipping_method_amounts_amounts check (
                    (free_over_amount is null or free_over_amount > 0)
                    and (backup_amount is null or backup_amount >= 0)
                    and (free_over_amount is not null or backup_amount is not null)
                )
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table shipping_method_amounts drop constraint shipping_method_amounts_amounts');
        Schema::table('shipping_method_amounts', static function (Blueprint $table): void {
            $table->dropColumn('backup_amount');
        });
        DB::statement('alter table shipping_method_amounts add constraint shipping_method_amounts_free_over_positive check (free_over_amount > 0)');

        DB::statement('alter table shipping_methods drop constraint shipping_methods_backup, drop constraint shipping_methods_carrier, drop constraint shipping_methods_pricing');
        Schema::table('shipping_methods', static function (Blueprint $table): void {
            $table->dropColumn(['carrier', 'carrier_service', 'backup_amount']);
        });
        DB::statement("alter table shipping_methods add constraint shipping_methods_pricing check (pricing in ('flat', 'weight', 'order_value'))");
    }
};
