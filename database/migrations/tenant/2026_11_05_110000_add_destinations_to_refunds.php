<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Where a refund goes (Step 18b). Back to the original payment, as every
 * earlier refund did, or to a module's destination the customer chose (such
 * as store credit), which pays it out itself, never through a gateway. A
 * refund to a destination still draws its parts from the order's payments,
 * so what each payment can give back stays exact, and each part keeps the
 * module's reference for the payout once it went through. That reference is
 * set once and never changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', static function (Blueprint $table): void {
            $table->string('destination', 64)->default('original_payment')->comment('Where it goes: "original_payment" (back the way each payment came) or a module\'s refund destination key, such as "store_credit".');
        });

        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->string('destination_reference', 128)->nullable()->comment('The module\'s reference for paying it out, for a refund to a module\'s destination; set once, when it went through.');
        });

        DB::unprepared(<<<'SQL'
            alter table refunds add constraint refunds_destination_format check (destination ~ '^[a-z][a-z0-9_]{0,63}$');

            create function payment_refunds_destination_reference_set_once() returns trigger language plpgsql as $$
            begin
                if old.destination_reference is not null and new.destination_reference is distinct from old.destination_reference then
                    raise exception 'A refund''s destination reference is set once and never changes.' using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;

            create trigger payment_refunds_destination_reference_set_once before update on payment_refunds
                for each row execute function payment_refunds_destination_reference_set_once();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop trigger if exists payment_refunds_destination_reference_set_once on payment_refunds;
            drop function if exists payment_refunds_destination_reference_set_once();
            alter table refunds drop constraint if exists refunds_destination_format;
            SQL);

        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->dropColumn('destination_reference');
        });

        Schema::table('refunds', static function (Blueprint $table): void {
            $table->dropColumn('destination');
        });
    }
};
