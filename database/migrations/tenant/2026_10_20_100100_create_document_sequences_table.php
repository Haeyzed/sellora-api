<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The last number given to an invoice or credit note in each series (Step
 * 15b): one row per kind and year ("2026"), or "all" when the store numbers
 * continuously. A number is taken under the row's lock, in the transaction
 * that saves the document, so a rollback gives it back and numbers never have
 * gaps (a database sequence would skip numbers on rollback).
 *
 * The database keeps the series gap-free too: a row only ever moves on by
 * exactly one, and is never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', static function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16)->comment('invoice or credit_note');
            $table->string('series', 8)->comment('The year, such as "2026", or "all" when numbering continuously');
            $table->bigInteger('last_number')->default(0);
            $table->timestampsTz();
            $table->unique(['kind', 'series']);
        });

        DB::statement(<<<'SQL'
            alter table document_sequences
                add constraint document_sequences_kind_check check (kind in ('invoice', 'credit_note')),
                add constraint document_sequences_series_check check (series = 'all' or series ~ '^[0-9]{4}$'),
                add constraint document_sequences_last_number_check check (last_number >= 0)
            SQL);

        DB::unprepared(<<<'SQL'
            create function document_sequences_move_on_by_one() returns trigger language plpgsql as $$
            begin
                if tg_op <> 'UPDATE' then
                    raise exception 'Document numbers are never given back; % is refused.', tg_op using errcode = 'check_violation';
                end if;

                if new.kind is distinct from old.kind or new.series is distinct from old.series
                    or new.last_number not in (old.last_number, old.last_number + 1) then
                    raise exception 'A document series only moves on by one number at a time.' using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;

            create trigger document_sequences_move_on_by_one before update or delete on document_sequences
                for each row execute function document_sequences_move_on_by_one();
            create trigger document_sequences_are_never_emptied before truncate on document_sequences
                for each statement execute function document_sequences_move_on_by_one();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        DB::unprepared('drop function if exists document_sequences_move_on_by_one() cascade;');
    }
};
