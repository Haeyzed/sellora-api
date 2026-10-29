<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Each attempt to refund a return through Payments (Step 18), numbered from
 * 1 within the return. An attempt is saved before Payments is asked, with
 * the idempotency key "return:{return public ID}:{attempt}" and exactly what
 * it asks for, so asking again after a crash or a timeout sends the same
 * request under the same key and never refunds twice. A new attempt is made
 * only once the last one definitely got nothing back: Payments refused it,
 * or every part of its refund failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_refunds', static function (Blueprint $table): void {
            $table->comment('Each attempt to refund a return through Payments, saved before Payments is asked.');
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $table->smallInteger('attempt')->comment('Which attempt of the return this is, from 1.');
            $table->string('idempotency_key', 64)->unique()->comment('Sent to Payments as the refund\'s idempotency key: "return:{return public ID}:{attempt}".');
            $table->foreignId('refund_id')->nullable()->unique()->comment('The refund Payments made for it; null until Payments answered, and when it refused.')->constrained('refunds')->restrictOnDelete();
            $table->string('status', 16)->default('started')->comment('One of App\Tenant\Returns\Enums\ReturnRefundStatus, read from the refund\'s parts in Payments.');
            $table->string('refusal_code', 64)->nullable()->comment('The error code Payments (or Returns, for a module\'s part) refused it with.');
            $table->boolean('shipping')->comment('Whether it gives back shipping too.');
            $table->bigInteger('withheld_amount')->default(0)->comment('What it keeps back from the lines, in minor units of the order\'s currency; 0 when nothing.');
            $table->string('withheld_reason', 500)->nullable()->comment('Why it keeps something back; set exactly when it does.');
            $table->string('requested_by_type', 64)->comment('Account type of the staff member who asked for it.');
            $table->string('requested_by_id', 64)->comment('Public ID of the staff member who asked for it.');
            $table->timestampsTz();
            $table->unique(['return_id', 'attempt']);
        });

        DB::statement(<<<'SQL'
            alter table return_refunds
                add constraint return_refunds_attempt_positive check (attempt >= 1),
                add constraint return_refunds_status_check check (status in ('started', 'refused', 'pending', 'in_review', 'succeeded', 'failed', 'mixed')),
                add constraint return_refunds_refused check ((status = 'refused') = (refusal_code is not null)),
                add constraint return_refunds_refund_known check ((status in ('started', 'refused')) = (refund_id is null)),
                add constraint return_refunds_withheld check ((withheld_amount = 0 and withheld_reason is null) or (withheld_amount > 0 and withheld_reason is not null))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('return_refunds');
    }
};
