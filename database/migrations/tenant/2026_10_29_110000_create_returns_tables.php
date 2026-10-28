<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Returns (Step 18): a buyer, or staff for them, sending units of an
 * order's lines back to be refunded.
 *
 *   requested → approved | rejected | cancelled
 *   approved  → received | cancelled | completed (the buyer keeps the items)
 *   received  → inspected
 *   inspected → completed (its refund went back) | rejected
 *
 * Each return is numbered within its order from 1. Its lines say how many
 * units of which order lines come back and, once inspected, how many went
 * back on the shelf and how many were written off. Every status change is
 * kept in return_status_changes, which a trigger makes append-only. A store's
 * return settings are one row, absent until staff first change them (the
 * defaults are in config/returns.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createReturns();
        $this->createReturnLines();
        $this->createReturnStatusChanges();
        $this->createReturnSettings();
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists return_status_changes_never_emptied on return_status_changes; drop trigger if exists return_status_changes_append_only on return_status_changes; drop function if exists return_status_changes_append_only();');
        Schema::dropIfExists('return_settings');
        Schema::dropIfExists('return_status_changes');
        Schema::dropIfExists('return_lines');
        Schema::dropIfExists('returns');
    }

    private function createReturns(): void
    {
        Schema::create('returns', static function (Blueprint $table): void {
            $table->comment('A buyer, or staff for them, sending units of an order\'s lines back to be refunded.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->smallInteger('sequence')->comment('Which return of the order this is, from 1; shown with the order number, such as "#1042-R1".');
            $table->string('status', 16)->default('requested')->comment('One of App\Tenant\Returns\Enums\ReturnStatus.');
            $table->string('resolution', 16)->default('refund')->comment('How it is settled: one of App\Tenant\Returns\Enums\ReturnResolution (exchanges come in Step 18b).');
            $table->string('reason', 32)->comment('Why: one of App\Tenant\Returns\Enums\ReturnReason.');
            $table->string('note', 1000)->nullable()->comment('What the buyer or staff added about the reason; personal data, cleared when the order is anonymised.');
            $table->boolean('is_test')->comment('Copied from its order: a test order\'s return never counts as a sale reduction in reports.');
            $table->boolean('keep_items')->default(false)->comment('Staff approved it without the items coming back: refunded with no stock moved.');
            $table->string('outside_window_reason', 500)->nullable()->comment('Why staff started it after the return window closed.');
            $table->string('undelivered_reason', 500)->nullable()->comment('Why staff started it for units shipped but never marked delivered.');
            $table->string('rejection_reason', 500)->nullable()->comment('Why staff rejected it.');
            $table->foreignId('stock_location_id')->nullable()->comment('Where its units were inspected and restocked or written off.')->constrained('stock_locations')->restrictOnDelete();
            $table->string('requested_by_type', 64)->nullable()->comment('Account type of the customer or staff member who asked; null for a guest with the order\'s access token.');
            $table->string('requested_by_id', 64)->nullable()->comment('Public ID of who asked; null for a guest.');
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('received_at')->nullable()->comment('When the items arrived at the store.');
            $table->timestampTz('inspected_at')->nullable();
            $table->timestampTz('completed_at')->nullable()->comment('When its refund went back to the buyer.');
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('anonymised_at')->nullable()->comment('When its note and photos were cleared with its order\'s personal data.');
            $table->timestampsTz();
            $table->unique(['order_id', 'sequence']);
            $table->index(['status', 'id']);
        });

        DB::statement(<<<'SQL'
            alter table returns
                add constraint returns_status_check check (status in ('requested', 'approved', 'rejected', 'received', 'inspected', 'completed', 'cancelled')),
                add constraint returns_resolution_check check (resolution in ('refund')),
                add constraint returns_reason_check check (reason in ('damaged', 'wrong_item', 'not_as_described', 'does_not_fit', 'changed_mind', 'other')),
                add constraint returns_sequence_positive check (sequence >= 1),
                add constraint returns_requested_by_complete check ((requested_by_type is null) = (requested_by_id is null)),
                add constraint returns_approved check (status not in ('approved', 'received', 'inspected', 'completed') or approved_at is not null),
                add constraint returns_received check ((status not in ('received', 'inspected') or received_at is not null) and (received_at is null or approved_at is not null)),
                add constraint returns_inspected check ((status <> 'inspected' or inspected_at is not null) and (inspected_at is null or (received_at is not null and stock_location_id is not null))),
                add constraint returns_kept_items_never_come_back check (keep_items = false or (received_at is null and inspected_at is null)),
                add constraint returns_completed check ((status = 'completed') = (completed_at is not null) and (status <> 'completed' or keep_items = true or inspected_at is not null)),
                add constraint returns_rejected check ((status = 'rejected') = (rejected_at is not null and rejection_reason is not null)),
                add constraint returns_cancelled check ((status = 'cancelled') = (cancelled_at is not null) and (status <> 'cancelled' or received_at is null))
            SQL);
    }

    private function createReturnLines(): void
    {
        Schema::create('return_lines', static function (Blueprint $table): void {
            $table->comment('How many units of one order line a return sends back, and what became of them once inspected.');
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $table->foreignId('order_line_id')->constrained('order_lines')->restrictOnDelete();
            $table->integer('quantity')->comment('Units coming back, never more than can still be returned of the line.');
            $table->integer('restocked_quantity')->nullable()->comment('Of those, how many went back on the shelf; null until inspected.');
            $table->integer('written_off_quantity')->nullable()->comment('Of those, how many came back damaged and were written off; null until inspected.');
            $table->timestampsTz();
            $table->unique(['return_id', 'order_line_id']);
            $table->index('order_line_id');
        });

        DB::statement(<<<'SQL'
            alter table return_lines
                add constraint return_lines_quantity_positive check (quantity > 0),
                add constraint return_lines_inspected_together check ((restocked_quantity is null) = (written_off_quantity is null)),
                add constraint return_lines_inspected_add_up check (restocked_quantity is null or (restocked_quantity >= 0 and written_off_quantity >= 0 and restocked_quantity + written_off_quantity = quantity))
            SQL);
    }

    private function createReturnStatusChanges(): void
    {
        Schema::create('return_status_changes', static function (Blueprint $table): void {
            $table->comment('History of every return\'s status. Only ever added to: a trigger refuses edits and deletions.');
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $table->string('from_status', 16)->nullable()->comment('Null when the return was requested.');
            $table->string('to_status', 16);
            $table->string('changed_by_type', 64)->nullable()->comment('Account type of the customer or staff member who changed it; null for a guest or the system.');
            $table->string('changed_by_id', 64)->nullable()->comment('Public ID of who changed it; null for a guest or the system.');
            $table->timestampTz('created_at');
            $table->index(['return_id', 'id']);
        });

        DB::statement('alter table return_status_changes add constraint return_status_changes_changer_complete check ((changed_by_type is null) = (changed_by_id is null))');
        DB::unprepared(<<<'SQL'
            create function return_status_changes_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'Return status history is append-only: it can''t be changed or deleted.';
            end;
            $$;
            create trigger return_status_changes_append_only before update or delete on return_status_changes
                for each row execute function return_status_changes_append_only();
            create trigger return_status_changes_never_emptied before truncate on return_status_changes
                for each statement execute function return_status_changes_append_only();
            SQL);
    }

    private function createReturnSettings(): void
    {
        Schema::create('return_settings', static function (Blueprint $table): void {
            $table->comment('How the store takes returns. One row, absent until staff first change it; the defaults are in config/returns.php.');
            $table->smallInteger('id')->primary();
            $table->smallInteger('window_days')->comment('How many days after a line\'s delivery a buyer may ask to return it; 0 means only staff can start returns.');
            $table->boolean('customer_requests')->comment('Whether customers and guests may ask for returns themselves; staff always can.');
            $table->boolean('photos_required')->comment('Whether a buyer must add a photo when the reason is damaged or the wrong item.');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            alter table return_settings
                add constraint return_settings_single_row check (id = 1),
                add constraint return_settings_window_days check (window_days between 0 and 365)
            SQL);
    }
};
