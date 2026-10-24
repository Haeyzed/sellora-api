<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Refunds are never sent blindly (Step 16b.3). Not every gateway refuses the
 * same refund twice (Paystack creates a second one), so each part of a refund
 * counts its sends, claimed before the gateway is called: once, and again at
 * most once, only after the gateway twice reported having no such refund, a
 * while apart (not_found_at is the first time). A part the gateway's answers
 * can't settle goes to staff review and is never sent again.
 *
 *   send_attempts  0 → 1 → 2, never back
 *   needs_review_at, review_reason  set once, never cleared
 *
 * Parts already made are taken as sent once, so none is sent again unasked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->unsignedSmallInteger('send_attempts')->default(0)->comment('How often it was claimed for sending to the gateway');
            $table->timestampTz('not_found_at')->nullable()->comment('When the gateway first reported having no such refund, since it was last sent');
            $table->timestampTz('needs_review_at')->nullable();
            $table->string('review_reason', 32)->nullable();
        });

        DB::table('payment_refunds')->whereNull('recorded_by_type')->update(['send_attempts' => 1]);

        DB::statement(<<<'SQL'
            alter table payment_refunds
                add constraint payment_refunds_send_attempts check (send_attempts between 0 and 2),
                add constraint payment_refunds_review_complete check ((needs_review_at is null) = (review_reason is null))
            SQL);

        DB::unprepared(<<<'SQL'
            create function payment_refunds_sends_only_grow() returns trigger language plpgsql as $$
            begin
                if new.send_attempts < old.send_attempts then
                    raise exception 'A refund''s sends are never taken back.' using errcode = 'check_violation';
                end if;

                if old.needs_review_at is not null and row(new.needs_review_at, new.review_reason) is distinct from row(old.needs_review_at, old.review_reason) then
                    raise exception 'A refund sent to review stays there.' using errcode = 'check_violation';
                end if;

                return new;
            end;
            $$;

            create trigger payment_refunds_sends_only_grow before update on payment_refunds
                for each row execute function payment_refunds_sends_only_grow();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop trigger if exists payment_refunds_sends_only_grow on payment_refunds;
            drop function if exists payment_refunds_sends_only_grow();
            SQL);

        Schema::table('payment_refunds', static function (Blueprint $table): void {
            $table->dropColumn(['send_attempts', 'not_found_at', 'needs_review_at', 'review_reason']);
        });
    }
};
