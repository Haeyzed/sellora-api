<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Codes customers enter to get a code promotion (Step 11). Kept as staff
 * wrote them and matched ignoring case, so no two codes in a store may
 * differ only by case: the unique index is on upper(code).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotion_codes', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->string('code', 32);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('promotion_id');
        });

        DB::statement('create unique index promotion_codes_code_unique on promotion_codes (upper(code))');
        DB::statement(<<<'SQL'
            alter table promotion_codes
                add constraint promotion_codes_format check (code ~ '^[A-Za-z0-9_-]{3,32}$'),
                add constraint promotion_codes_usage_limit_positive check (usage_limit is null or usage_limit > 0)
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_codes');
    }
};
