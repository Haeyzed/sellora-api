<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Staff alerts (Step 20a): what needs a staff member's attention, raised
 * from domain events (a payment to review, a refund that failed, stock
 * running low). Each alert is listed for staff holding its permission and
 * emailed to them. Its repeat key is unique, so the same alert about the
 * same thing is raised once (or once a day), even when two events arrive at
 * the same moment. Alerts hold no personal data: public IDs, order numbers,
 * amounts and SKUs only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_alerts', static function (Blueprint $table): void {
            $table->comment('What needs staff attention, raised from domain events, listed for staff holding its permission and emailed to them. Holds no personal data.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('The alert\'s public ID.');
            $table->string('kind', 48)->comment('What it is about: one of App\Tenant\Messaging\Enums\StaffAlertKind.');
            $table->string('repeat_key', 191)->unique()->comment('Its kind, the thing it is about and (for kinds that may come again) the day: the same alert is raised once per key.');
            $table->string('subject_type', 40)->comment('What it is about, such as "payment" or "product_variant".');
            $table->string('subject_id', 64)->comment('The public ID (or, for a gateway, the key) of what it is about.');
            $table->jsonb('details')->comment('What its text shows: order numbers, amounts in minor units with their currency, SKUs. Never personal data.');
            $table->timestampTz('emailed_at')->nullable()->comment('When it was emailed to staff; null when it was not (switched off, or over the hourly cap).');
            $table->timestampTz('created_at');
            $table->index(['kind', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_alerts');
    }
};
