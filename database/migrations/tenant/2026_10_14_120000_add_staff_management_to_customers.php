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
            // Name, email and phone in lower case, kept current by the Customer model, for staff search.
            $table->text('search_text')->default('');
        });

        DB::statement("update customers set search_text = lower(concat_ws(' ', name, email, phone))");
        DB::statement('create index customers_search_text_trigram on customers using gin (search_text gin_trgm_ops)');

        // What staff write about a customer. Customers can ask to see them (section 3.3), so they are written for that.
        Schema::create('customer_notes', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // Staff accounts are deactivated, never deleted; the note stays if one ever is.
            $table->foreignId('author_staff_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->text('body');
            $table->timestampTz('created_at');

            $table->index(['customer_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
        DB::statement('drop index if exists customers_search_text_trigram');

        Schema::table('customers', static function (Blueprint $table): void {
            $table->dropColumn('search_text');
        });
    }
};
