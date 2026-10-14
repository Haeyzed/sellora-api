<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Every grant or withdrawal of marketing consent, as it happened (section 3.3). The latest event for a channel and contact is the current answer.
        Schema::create('marketing_consents', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            // Null for a guest who answered at checkout: the event belongs to the contact alone.
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('channel', 20);
            // An email in lower case, or a phone in E.164.
            $table->string('contact', 254);
            $table->string('status', 20);
            $table->string('source', 20);
            // The exact text shown when the answer was given, and its language.
            $table->text('wording')->nullable();
            $table->string('locale', 10)->nullable();
            // Staff only ever record a withdrawal the customer asked for.
            $table->foreignId('recorded_by_staff_id')->nullable()->constrained('staff_members');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['channel', 'contact', 'id']);
            $table->index(['customer_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            alter table marketing_consents
                add constraint marketing_consents_channel_check check (channel in ('email', 'sms')),
                add constraint marketing_consents_status_check check (status in ('granted', 'withdrawn')),
                add constraint marketing_consents_source_check check (source in ('account', 'checkout', 'staff')),
                add constraint marketing_consents_granted_wording_check check (status <> 'granted' or (wording is not null and locale is not null)),
                add constraint marketing_consents_staff_withdraw_only_check check (recorded_by_staff_id is null or (status = 'withdrawn' and source = 'staff')),
                add constraint marketing_consents_staff_source_check check (source <> 'staff' or recorded_by_staff_id is not null);

            -- Consent events are evidence, so they are never changed. Only erasure deletes them, and it says so first
            -- with set_config('app.erasing_personal_data', 'on', true), which lasts until its transaction ends.
            create function marketing_consents_are_append_only() returns trigger language plpgsql as $$
            begin
                if tg_op = 'UPDATE' then
                    raise exception 'marketing consent events are never changed' using errcode = 'check_violation';
                end if;

                if coalesce(current_setting('app.erasing_personal_data', true), '') <> 'on' then
                    raise exception 'marketing consent events are deleted only by erasure' using errcode = 'check_violation';
                end if;

                return case when tg_op = 'DELETE' then old else null end;
            end;
            $$;

            create trigger marketing_consents_append_only_rows
                before update or delete on marketing_consents
                for each row execute function marketing_consents_are_append_only();

            create trigger marketing_consents_append_only_truncate
                before truncate on marketing_consents
                for each statement execute function marketing_consents_are_append_only();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_consents');
        DB::statement('drop function if exists marketing_consents_are_append_only()');
    }
};
