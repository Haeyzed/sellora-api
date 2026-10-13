<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Market exchange rates, fetched hourly for every store (sections 2.1 and
 * 3.3): how many units of each currency one US dollar bought, and when.
 * Append-only: every fetch adds rows, a trigger refuses updates, and only the
 * retention policy deletes old rows. Stores read them only through
 * App\Shared\Money\ExchangeRates. The function is replaced rather than
 * created, because migrate:fresh drops tables but leaves functions behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', static function (Blueprint $table): void {
            $table->id();
            $table->char('currency', 3);
            $table->decimal('units_per_usd', 24, 12);
            $table->string('source', 32);
            $table->timestampTz('fetched_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['currency', 'fetched_at']);
            $table->index('fetched_at');
        });
        DB::statement("alter table exchange_rates add constraint exchange_rates_currency_code check (currency ~ '^[A-Z]{3}$')");
        DB::statement('alter table exchange_rates add constraint exchange_rates_rate_positive check (units_per_usd > 0)');

        DB::unprepared(<<<'SQL'
            create or replace function exchange_rates_never_change() returns trigger language plpgsql as $$
            begin
                raise exception 'Exchange rates are never changed; record a new fetch instead.';
            end;
            $$;

            create trigger exchange_rates_never_change before update on exchange_rates
                for each row execute function exchange_rates_never_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists exchange_rates_never_change on exchange_rates; drop function if exists exchange_rates_never_change();');
        Schema::dropIfExists('exchange_rates');
    }
};
