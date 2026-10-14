<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Customer groups (section 10, built with Promotions): named groups staff
 * put customers in by hand, such as "Wholesale" or "VIP", which promotions
 * can be limited to. Guests are never in a group.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_groups', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        DB::statement('create unique index customer_groups_name_unique on customer_groups (lower(name))');

        Schema::create('customer_group_members', static function (Blueprint $table): void {
            $table->foreignId('customer_group_id')->constrained('customer_groups')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['customer_group_id', 'customer_id']);
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_group_members');
        Schema::dropIfExists('customer_groups');
    }
};
