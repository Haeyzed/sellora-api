<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Where each attempt to refund a return asks Payments to send the money
 * (Step 18b): the customer's latest choice when the attempt was saved, or
 * back to the original payment. Saved with the attempt, so asking Payments
 * again under the same key sends exactly the same request; a later attempt
 * follows whatever the customer chose by then. Earlier attempts all went
 * back to the original payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_refunds', static function (Blueprint $table): void {
            $table->string('destination', 64)->default('original_payment')->comment('Where it asks the refund to go: "original_payment" or a module\'s refund destination key, the customer\'s choice when it was saved.');
        });

        DB::statement("alter table return_refunds add constraint return_refunds_destination_format check (destination ~ '^[a-z][a-z0-9_]{0,63}$')");
    }

    public function down(): void
    {
        Schema::table('return_refunds', static function (Blueprint $table): void {
            $table->dropColumn('destination');
        });
    }
};
