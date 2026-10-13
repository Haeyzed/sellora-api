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
        Schema::table('customers', static function (Blueprint $table): void {
            // E.164, such as +2348012345678.
            $table->string('phone', 16)->nullable()->after('email');
            // One of the store's content languages; emails to the customer use it.
            $table->string('locale', 10)->nullable()->after('phone');
            $table->timestampTz('email_verified_at')->nullable()->after('locale');
        });

        // One pending link per customer and purpose: sending a new one replaces the old.
        Schema::create('customer_email_tokens', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('purpose', 20);
            // The address the link was sent to: the current one to verify it, or the new one for a change.
            $table->string('email', 254);
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');

            $table->unique(['customer_id', 'purpose']);
        });

        DB::statement("alter table customer_email_tokens add constraint customer_email_tokens_purpose_check check (purpose in ('verification', 'email_change'))");
        DB::statement("alter table customers add constraint customers_phone_e164_check check (phone is null or phone ~ '^\\+[1-9][0-9]{6,14}$')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_email_tokens');

        DB::statement('alter table customers drop constraint if exists customers_phone_e164_check');

        Schema::table('customers', static function (Blueprint $table): void {
            $table->dropColumn(['phone', 'locale', 'email_verified_at']);
        });
    }
};
