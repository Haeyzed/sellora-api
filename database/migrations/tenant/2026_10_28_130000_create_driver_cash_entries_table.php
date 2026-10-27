<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The cash each driver holds (Step 17, section 10): an append-only ledger.
 * Cash a driver collects on delivery is added in the same transaction as the
 * payment it pays; cash they hand over to staff is taken off. What a driver
 * holds in a currency is what they collected in it less what they handed
 * over in it; currencies are never mixed. A handover is never more than the
 * driver holds, checked under the driver's lock. A trigger refuses edits and
 * deletions, so corrections are new entries. A business record: kept when
 * the driver's personal data is erased, still pointing at the erased driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_cash_entries', static function (Blueprint $table): void {
            $table->comment('Cash each driver collected on delivery or handed over to staff. Only ever added to: a trigger refuses edits and deletions.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->string('type', 16)->comment('One of App\Tenant\Delivery\Enums\DriverCashEntryType: collected (the driver took it) or handed_over (the driver gave it to staff).');
            $table->bigInteger('cash_amount')->comment('Always positive, in minor units of cash_currency; the type says which way it went.');
            $table->char('cash_currency', 3)->comment('ISO 4217.');
            $table->foreignId('delivery_id')->nullable()->comment('The delivery it was collected on; collected entries only.')->constrained('deliveries')->restrictOnDelete();
            $table->string('payment_public_id', 64)->nullable()->comment('Public ID of the payment it paid; collected entries only.');
            $table->string('recorded_by_type', 64)->comment('Account type of the driver or staff member who recorded it.');
            $table->string('recorded_by_id', 64)->comment('Public ID of who recorded it.');
            $table->timestampTz('created_at');
            $table->index(['driver_id', 'cash_currency']);
            $table->index(['driver_id', 'id']);
        });

        DB::statement(<<<'SQL'
            alter table driver_cash_entries
                add constraint driver_cash_entries_type_check check (type in ('collected', 'handed_over')),
                add constraint driver_cash_entries_amount_positive check (cash_amount > 0),
                add constraint driver_cash_entries_currency_format check (cash_currency ~ '^[A-Z]{3}$'),
                add constraint driver_cash_entries_collected_on_delivery check ((type = 'collected') = (delivery_id is not null and payment_public_id is not null))
            SQL);
        DB::statement("create unique index driver_cash_entries_one_collection_per_delivery on driver_cash_entries (delivery_id) where type = 'collected'");
        DB::unprepared(<<<'SQL'
            create function driver_cash_entries_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'The driver cash ledger is append-only: entries can''t be changed or deleted.';
            end;
            $$;
            create trigger driver_cash_entries_append_only before update or delete on driver_cash_entries
                for each row execute function driver_cash_entries_append_only();
            create trigger driver_cash_entries_never_emptied before truncate on driver_cash_entries
                for each statement execute function driver_cash_entries_append_only();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists driver_cash_entries_never_emptied on driver_cash_entries; drop trigger if exists driver_cash_entries_append_only on driver_cash_entries; drop function if exists driver_cash_entries_append_only();');
        Schema::dropIfExists('driver_cash_entries');
    }
};
