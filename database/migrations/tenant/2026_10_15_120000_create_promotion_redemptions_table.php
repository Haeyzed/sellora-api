<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The promotion use ledger (Step 11): one row per promotion a checkout or
 * order uses. Usage limits are counted from it, never from a separate counter
 * that can drift. A checkout's use is reserved until its hold runs out, then
 * released; an order's is committed once paid, and released again if the
 * order is cancelled. A holder has at most one live use per promotion.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotion_redemptions', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->foreignId('promotion_code_id')->nullable()->constrained('promotion_codes')->restrictOnDelete();
            $table->string('holder_type', 20);
            $table->string('holder_id', 64);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('email', 254)->nullable();
            $table->string('status', 10);
            $table->char('currency', 3);
            $table->bigInteger('discount_amount');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();

            $table->index(['promotion_id', 'status']);
            $table->index(['promotion_code_id', 'status']);
            $table->index(['promotion_id', 'customer_id']);
            $table->index(['holder_type', 'holder_id']);
        });

        DB::statement("create unique index promotion_redemptions_one_live_per_holder on promotion_redemptions (holder_type, holder_id, promotion_id) where status <> 'released'");
        DB::statement('create index promotion_redemptions_by_email on promotion_redemptions (promotion_id, email) where email is not null');
        DB::statement(<<<'SQL'
            alter table promotion_redemptions
                add constraint promotion_redemptions_status_check check (status in ('reserved', 'committed', 'released')),
                add constraint promotion_redemptions_discount_not_negative check (discount_amount >= 0),
                add constraint promotion_redemptions_currency_code check (currency ~ '^[A-Z]{3}$'),
                add constraint promotion_redemptions_email_lower_case check (email is null or email = lower(email)),
                add constraint promotion_redemptions_committed_never_expire check (status <> 'committed' or expires_at is null),
                add constraint promotion_redemptions_settled_when_not_reserved check ((status = 'reserved') = (settled_at is null))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
    }
};
