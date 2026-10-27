<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Deliveries (Step 17): one of the store's own drivers taking a shipment to
 * its buyer. assigned → picked_up → on_the_way → delivered, or failed on the
 * way; an assigned one can be cancelled. A failed one stays with the driver
 * until staff resolve it: another attempt by the same driver, or the goods
 * brought back to the store. A shipment has at most one open delivery at a
 * time (assigned, picked up, on the way, or failed and unresolved), which a
 * partial unique index guarantees. Every status change is kept in
 * delivery_status_changes, which a trigger makes append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', static function (Blueprint $table): void {
            $table->comment('One of the store\'s own drivers taking a shipment to its buyer. A shipment has at most one open delivery at a time.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->smallInteger('attempt')->comment('Which delivery of the shipment this is, from 1.');
            $table->string('status', 16)->default('assigned')->comment('One of App\Tenant\Delivery\Enums\DeliveryStatus.');
            $table->string('failure_reason', 32)->nullable()->comment('Why it failed: one of App\Tenant\Delivery\Enums\DeliveryFailureReason.');
            $table->string('failure_note', 500)->nullable()->comment('What the driver or staff added about a failure; personal data, erased with the driver\'s or buyer\'s data.');
            $table->string('recipient_name', 255)->nullable()->comment('Who took the goods, as the driver recorded it; personal data.');
            $table->timestampTz('assigned_at');
            $table->timestampTz('picked_up_at')->nullable()->comment('When the driver took the goods from the store.');
            $table->timestampTz('on_the_way_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('resolved_at')->nullable()->comment('When staff resolved a failed delivery.');
            $table->string('resolution', 16)->nullable()->comment('How a failed delivery was resolved: "retried" (another attempt) or "returned" (goods back at the store).');
            $table->string('assigned_by_type', 64)->comment('Account type of the staff member who assigned it.');
            $table->string('assigned_by_id', 64)->comment('Public ID of the staff member who assigned it.');
            $table->timestampsTz();
            $table->unique(['shipment_id', 'attempt']);
            $table->index(['driver_id', 'status', 'id']);
            $table->index(['status', 'id']);
        });

        DB::statement(<<<'SQL'
            alter table deliveries
                add constraint deliveries_status_check check (status in ('assigned', 'picked_up', 'on_the_way', 'delivered', 'failed', 'cancelled')),
                add constraint deliveries_failure_reason_check check (failure_reason is null or failure_reason in ('recipient_not_available', 'address_not_found', 'refused', 'unsafe', 'recipient_asked_to_reschedule', 'other')),
                add constraint deliveries_attempt_positive check (attempt >= 1),
                add constraint deliveries_failed check ((status = 'failed') = (failed_at is not null and failure_reason is not null)),
                add constraint deliveries_failure_note_with_reason check (failure_note is null or failure_reason is not null),
                add constraint deliveries_delivered check ((status = 'delivered') = (delivered_at is not null) and (status <> 'delivered' or recipient_name is not null)),
                add constraint deliveries_cancelled check ((status = 'cancelled') = (cancelled_at is not null)),
                add constraint deliveries_picked_up check ((picked_up_at is not null) = (status in ('picked_up', 'on_the_way', 'delivered', 'failed'))),
                add constraint deliveries_resolved_only_failed check ((resolved_at is not null) = (resolution is not null) and (resolved_at is null or status = 'failed')),
                add constraint deliveries_resolution_check check (resolution is null or resolution in ('retried', 'returned'))
            SQL);
        DB::statement("create unique index deliveries_one_open_per_shipment on deliveries (shipment_id) where status in ('assigned', 'picked_up', 'on_the_way') or (status = 'failed' and resolved_at is null)");

        Schema::create('delivery_status_changes', static function (Blueprint $table): void {
            $table->comment('History of every delivery\'s status. Only ever added to: a trigger refuses edits and deletions.');
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->string('from_status', 16)->nullable()->comment('Null when the delivery was assigned.');
            $table->string('to_status', 16);
            $table->string('changed_by_type', 64)->nullable()->comment('Account type of the driver or staff member who changed it; null for the system.');
            $table->string('changed_by_id', 64)->nullable()->comment('Public ID of who changed it; null for the system.');
            $table->timestampTz('created_at');
            $table->index(['delivery_id', 'id']);
        });

        DB::statement('alter table delivery_status_changes add constraint delivery_status_changes_changer_complete check ((changed_by_type is null) = (changed_by_id is null))');
        DB::unprepared(<<<'SQL'
            create function delivery_status_changes_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'Delivery status history is append-only: it can''t be changed or deleted.';
            end;
            $$;
            create trigger delivery_status_changes_append_only before update or delete on delivery_status_changes
                for each row execute function delivery_status_changes_append_only();
            create trigger delivery_status_changes_never_emptied before truncate on delivery_status_changes
                for each statement execute function delivery_status_changes_append_only();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists delivery_status_changes_never_emptied on delivery_status_changes; drop trigger if exists delivery_status_changes_append_only on delivery_status_changes; drop function if exists delivery_status_changes_append_only();');
        Schema::dropIfExists('delivery_status_changes');
        Schema::dropIfExists('deliveries');
    }
};
