<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Payment webhooks and checks (Step 15.3).
 *
 * Every webhook whose signature checked out is recorded once per gateway
 * event ID, so a resend is recognised and nothing is done twice. What it
 * said is kept encrypted, and only for a while (retention); it is never
 * trusted: it only tells us which payment to go and ask the gateway about.
 *
 * "unresolved" is reached only by a gateway payment that is still pending
 * when checking gives up (config payments.checks.give_up_after_hours), and
 * records when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('payment_gateway_connections')->restrictOnDelete();
            $table->string('gateway', 64);
            $table->string('event_id', 255)->comment('The gateway\'s own, so a resend is handled once');
            $table->string('kind', 16);
            $table->string('reference', 64)->nullable()->comment('Ours, of the payment or refund it is about');
            $table->text('payload')->nullable()->comment('Encrypted; emptied after the retention period');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->unique(['gateway', 'event_id']);
            $table->index('received_at');
        });
        DB::statement("alter table payment_webhook_events add constraint payment_webhook_events_kind_check check (kind in ('payment', 'refund', 'dispute', 'ignored'))");

        DB::statement(<<<'SQL'
            alter table payments
                add constraint payments_unresolved_only_for_gateway check (status <> 'unresolved' or method = 'gateway'),
                add constraint payments_unresolved_at check (status <> 'unresolved' or unresolved_at is not null)
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table payments drop constraint payments_unresolved_only_for_gateway, drop constraint payments_unresolved_at');
        Schema::dropIfExists('payment_webhook_events');
    }
};
