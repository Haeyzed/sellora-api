<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Where a return's refund goes (Step 18b): each choice the customer made, or
 * staff recorded at the customer's request with a note saying how they
 * asked. The latest choice is the one the next refund attempt follows; with
 * none, the refund goes back to the original payment. Choices are only ever
 * added: a trigger refuses every change, except that erasing personal data
 * (app.erasing_personal_data, set by the Shared erasure runner) may clear a
 * note when its order is anonymised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_refund_choices', static function (Blueprint $table): void {
            $table->comment('Where a return\'s refund goes: each choice the customer made, or staff recorded for them. The latest counts. Only ever added to.');
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $table->string('destination', 64)->comment('"original_payment" (core\'s own) or a module\'s refund destination key, such as "store_credit".');
            $table->string('chosen_by_type', 64)->comment('Account type of who chose: the customer, or staff recording the customer\'s request.');
            $table->string('chosen_by_id', 64)->comment('Public ID of who chose.');
            $table->string('note', 500)->nullable()->comment('How the customer asked, when staff recorded it (required then); personal data, cleared when the order is anonymised.');
            $table->timestampTz('anonymised_at')->nullable()->comment('When its note was cleared with its order\'s personal data.');
            $table->timestampTz('created_at');
            $table->index(['return_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            alter table return_refund_choices
                add constraint return_refund_choices_destination_format check (destination ~ '^[a-z][a-z0-9_]{0,63}$'),
                add constraint return_refund_choices_staff_note check (chosen_by_type <> 'staff_member' or note is not null or anonymised_at is not null);

            create function return_refund_choices_append_only() returns trigger language plpgsql as $$
            begin
                if tg_op = 'UPDATE'
                    and coalesce(current_setting('app.erasing_personal_data', true), '') = 'on'
                    and new.note is null and new.anonymised_at is not null
                    and row(new.id, new.return_id, new.destination, new.chosen_by_type, new.chosen_by_id, new.created_at)
                        is not distinct from row(old.id, old.return_id, old.destination, old.chosen_by_type, old.chosen_by_id, old.created_at) then
                    return new;
                end if;

                raise exception 'Refund destination choices are only ever added: % is refused.', tg_op using errcode = 'check_violation';
            end;
            $$;

            create trigger return_refund_choices_append_only before update or delete on return_refund_choices
                for each row execute function return_refund_choices_append_only();
            create trigger return_refund_choices_never_emptied before truncate on return_refund_choices
                for each statement execute function return_refund_choices_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('return_refund_choices');
        DB::unprepared('drop function if exists return_refund_choices_append_only();');
    }
};
