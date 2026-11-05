<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Exchanges in Returns (Step 18d): a return settled by sending other items
 * in place of the ones that came back.
 *
 * - A return's resolution can be exchange, each of its lines then naming the
 *   variant sent in its place, and it waits as awaiting_exchange while its
 *   replacement order is unpaid.
 * - Faulty joins the reasons; the buyer's reason is a claim, and staff
 *   confirm whether the store was at fault (when they inspect the items, or
 *   approve without them coming back), recording why when they overrule it.
 *   Returns already inspected or kept take their reason as confirmed.
 * - Each confirmed exchange is a row in return_exchanges: what the returned
 *   items were worth, what they credited toward the replacement, what goes
 *   back to the buyer and what the store keeps.
 * - A refund attempt can give back an amount (an exchange's difference) as
 *   well as the return's lines.
 * - Whether a replacement's delivery is charged when the store wasn't at
 *   fault is a return setting, off by default.
 */
return new class extends Migration
{
    private const string STORES_FAULT = "reason in ('damaged', 'wrong_item', 'faulty', 'not_as_described')";

    public function up(): void
    {
        $this->changeReturns();
        $this->changeReturnLines();
        $this->createReturnExchanges();
        $this->changeReturnRefunds();

        Schema::table('return_settings', static function (Blueprint $table): void {
            $table->boolean('charge_shipping_on_exchanges')->default(false)->comment('Whether the buyer pays for an exchange\'s replacement delivery when the store wasn\'t at fault; it is always free when it was.');
        });
    }

    public function down(): void
    {
        Schema::table('return_settings', static fn (Blueprint $table) => $table->dropColumn('charge_shipping_on_exchanges'));

        DB::statement('alter table return_refunds drop constraint return_refunds_amount, drop constraint return_refunds_amount_alone');
        Schema::table('return_refunds', static fn (Blueprint $table) => $table->dropColumn('refund_amount'));

        Schema::dropIfExists('return_exchanges');

        DB::unprepared(<<<'SQL'
            drop trigger if exists return_lines_replacements_match on return_lines;
            drop trigger if exists returns_replacements_match on returns;
            drop function if exists return_replacements_match();
            SQL);
        Schema::table('return_lines', static fn (Blueprint $table) => $table->dropConstrainedForeignId('replacement_variant_id'));

        DB::statement(<<<'SQL'
            alter table returns
                drop constraint returns_status_check, drop constraint returns_resolution_check, drop constraint returns_reason_check,
                drop constraint returns_approved, drop constraint returns_awaiting_exchange, drop constraint returns_fault_overruled, drop constraint returns_fault_confirmed
            SQL);
        Schema::table('returns', static function (Blueprint $table): void {
            $table->dropColumn(['stores_fault', 'fault_overruled_reason']);
            $table->string('status', 16)->default('requested')->comment('One of App\Tenant\Returns\Enums\ReturnStatus.')->change();
        });
        Schema::table('return_status_changes', static function (Blueprint $table): void {
            $table->string('from_status', 16)->nullable()->comment('Null when the return was requested.')->change();
            $table->string('to_status', 16)->change();
        });
        DB::statement(<<<'SQL'
            alter table returns
                add constraint returns_status_check check (status in ('requested', 'approved', 'rejected', 'received', 'inspected', 'completed', 'cancelled')),
                add constraint returns_resolution_check check (resolution in ('refund')),
                add constraint returns_reason_check check (reason in ('damaged', 'wrong_item', 'not_as_described', 'does_not_fit', 'changed_mind', 'other')),
                add constraint returns_approved check (status not in ('approved', 'received', 'inspected', 'completed') or approved_at is not null)
            SQL);
    }

    private function changeReturns(): void
    {
        // "awaiting_exchange" is longer than the 16 characters statuses had.
        Schema::table('return_status_changes', static function (Blueprint $table): void {
            $table->string('from_status', 32)->nullable()->comment('Null when the return was requested.')->change();
            $table->string('to_status', 32)->change();
        });

        Schema::table('returns', static function (Blueprint $table): void {
            $table->string('status', 32)->default('requested')->comment('One of App\Tenant\Returns\Enums\ReturnStatus.')->change();
            $table->string('resolution', 16)->default('refund')->comment('How it is settled: one of App\Tenant\Returns\Enums\ReturnResolution.')->change();
            $table->boolean('stores_fault')->nullable()->after('reason')->comment('Whether staff confirmed the store was at fault: set when they inspect the items, or approve without them coming back; null until then. Only a confirmed fault gives a free like-for-like replacement and free delivery.');
            $table->string('fault_overruled_reason', 500)->nullable()->after('stores_fault')->comment('Why staff decided otherwise than the buyer\'s reason claimed; set exactly when they did.');
        });

        $fault = self::STORES_FAULT;

        // Returns already inspected or kept were judged on their reason: it stands as confirmed.
        DB::statement("update returns set stores_fault = ({$fault}) where inspected_at is not null or keep_items = true");

        DB::statement(<<<SQL
            alter table returns
                drop constraint returns_status_check, drop constraint returns_resolution_check, drop constraint returns_reason_check, drop constraint returns_approved,
                add constraint returns_status_check check (status in ('requested', 'approved', 'rejected', 'received', 'inspected', 'awaiting_exchange', 'completed', 'cancelled')),
                add constraint returns_resolution_check check (resolution in ('refund', 'exchange')),
                add constraint returns_reason_check check (reason in ('damaged', 'wrong_item', 'faulty', 'not_as_described', 'does_not_fit', 'changed_mind', 'other')),
                add constraint returns_approved check (status not in ('approved', 'received', 'inspected', 'awaiting_exchange', 'completed') or approved_at is not null),
                add constraint returns_awaiting_exchange check (status <> 'awaiting_exchange' or (resolution = 'exchange' and (inspected_at is not null or keep_items = true))),
                add constraint returns_fault_overruled check ((fault_overruled_reason is null) = (stores_fault is null or stores_fault = ({$fault}))),
                add constraint returns_fault_confirmed check ((inspected_at is null and keep_items = false) or stores_fault is not null)
            SQL);
    }

    private function changeReturnLines(): void
    {
        Schema::table('return_lines', static function (Blueprint $table): void {
            $table->foreignId('replacement_variant_id')->nullable()->after('order_line_id')
                ->comment('The variant sent in place of the returned units, as many of it, in an exchange; null for a refund. Set exactly when the return is an exchange')
                ->constrained('product_variants')->restrictOnDelete();
        });

        // A check can't read the return, so a trigger checked when the transaction commits keeps every line's replacement in step with its return's resolution.
        DB::unprepared(<<<'SQL'
            create function return_replacements_match() returns trigger language plpgsql as $$
            declare
                checked_return bigint;
            begin
                -- Each branch reads only the row it fires for: a return has no return_id, a line no resolution.
                if tg_table_name = 'returns' then
                    checked_return := new.id;
                else
                    checked_return := new.return_id;
                end if;

                if exists (
                    select 1 from return_lines join returns on returns.id = return_lines.return_id
                    where returns.id = checked_return
                        and (returns.resolution = 'exchange') <> (return_lines.replacement_variant_id is not null)
                ) then
                    raise exception 'Every line of an exchange names its replacement, and a refund''s lines name none.' using errcode = 'check_violation';
                end if;

                return null;
            end;
            $$;

            create constraint trigger return_lines_replacements_match after insert or update on return_lines
                deferrable initially deferred for each row execute function return_replacements_match();
            create constraint trigger returns_replacements_match after update of resolution on returns
                deferrable initially deferred for each row execute function return_replacements_match();
            SQL);
    }

    private function createReturnExchanges(): void
    {
        Schema::create('return_exchanges', static function (Blueprint $table): void {
            $table->comment('Each confirmed exchange of a return: its replacement order and how the returned items settle it. A return may be confirmed again after a replacement expired or was cancelled unpaid.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->comment('The replacement order.')->constrained('orders')->restrictOnDelete();
            $table->string('status', 16)->default('pending')->comment('One of App\Tenant\Returns\Enums\ReturnExchangeStatus.');
            $table->boolean('stores_fault')->comment('Whether the store was at fault, as staff confirmed it: a like-for-like replacement and its delivery were then free.');
            $table->boolean('shipping_charged')->comment('Whether the buyer paid for the replacement\'s delivery.');
            $table->char('currency', 3)->comment('The orders\' currency, ISO 4217.');
            $table->bigInteger('returned_value_amount')->comment('What the returned units cost the buyer after their discounts, in minor units: returned_value = credit + difference + kept.');
            $table->bigInteger('credit_amount')->comment('What the returned items pay toward the replacement order, in minor units: the exchange credit held on it.');
            $table->bigInteger('difference_amount')->comment('What goes back to the buyer, in minor units, to the destination they chose: a cheaper replacement\'s difference; 0 when none.');
            $table->bigInteger('kept_amount')->comment('What the store keeps of the returned items\' value, in minor units: what a free like-for-like replacement didn\'t use; 0 when none.');
            $table->foreignId('refund_id')->nullable()->unique()->comment('The refund of the original order\'s returned lines to the exchange, made once the replacement is paid; null until then.')->constrained('refunds')->restrictOnDelete();
            $table->string('confirmed_by_type', 64)->comment('Account type of the staff member who confirmed it.');
            $table->string('confirmed_by_id', 64)->comment('Public ID of the staff member who confirmed it.');
            $table->timestampTz('committed_at')->nullable()->comment('When the replacement was paid and the credit became final.');
            $table->timestampTz('released_at')->nullable()->comment('When the replacement expired or was cancelled unpaid, giving the credit back.');
            $table->timestampsTz();
            $table->index('return_id');
        });

        DB::statement(<<<'SQL'
            alter table return_exchanges
                add constraint return_exchanges_status_check check (status in ('pending', 'committed', 'released')),
                add constraint return_exchanges_committed check ((status = 'committed') = (committed_at is not null)),
                add constraint return_exchanges_released check ((status = 'released') = (released_at is not null)),
                add constraint return_exchanges_amounts check (credit_amount >= 0 and difference_amount >= 0 and kept_amount >= 0 and returned_value_amount = credit_amount + difference_amount + kept_amount),
                add constraint return_exchanges_kept_only_when_free check (kept_amount = 0 or stores_fault)
            SQL);
        DB::statement("create unique index return_exchanges_one_live_per_return on return_exchanges (return_id) where status <> 'released'");
    }

    private function changeReturnRefunds(): void
    {
        Schema::table('return_refunds', static function (Blueprint $table): void {
            $table->bigInteger('refund_amount')->nullable()->after('shipping')->comment('An amount it gives back instead of the return\'s lines, in minor units of the order\'s currency: an exchange\'s difference. Null when it gives back the lines');
        });

        DB::statement(<<<'SQL'
            alter table return_refunds
                add constraint return_refunds_amount check (refund_amount is null or refund_amount > 0),
                add constraint return_refunds_amount_alone check (refund_amount is null or (shipping = false and withheld_amount = 0))
            SQL);
    }
};
