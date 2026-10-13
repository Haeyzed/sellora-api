<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The currencies a store sells in besides its base currency, which lives in
 * its settings and is never listed here (section 3.3). Each has the
 * merchant's margin on the market rate, from 0 to 25%, kept apart from the
 * rate itself, or a manual rate the merchant set instead. Manual rates have
 * twelve decimal places, like market rates. A currency is deactivated rather
 * than deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_currencies', static function (Blueprint $table): void {
            $table->id();
            $table->char('currency_code', 3)->unique();
            $table->boolean('is_active')->default(true);
            $table->decimal('margin_percent', 6, 4)->default(0);
            $table->decimal('manual_rate', 24, 12)->nullable()->comment('Units of this currency per unit of the base currency');
            $table->timestampTz('manual_rate_set_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("alter table store_currencies add constraint store_currencies_currency_code check (currency_code ~ '^[A-Z]{3}$')");
        DB::statement('alter table store_currencies add constraint store_currencies_margin_range check (margin_percent >= 0 and margin_percent <= 25)');
        DB::statement('alter table store_currencies add constraint store_currencies_manual_rate_positive check (manual_rate is null or manual_rate > 0)');
        DB::statement('alter table store_currencies add constraint store_currencies_manual_rate_dated check ((manual_rate is null) = (manual_rate_set_at is null))');
    }

    public function down(): void
    {
        Schema::dropIfExists('store_currencies');
    }
};
