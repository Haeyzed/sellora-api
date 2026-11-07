<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A staff member's preferred language (Step 20a), for what the store sends
 * them outside a request (alerts sent from the queue or the scheduler), when
 * there is no Accept-Language to go by. Null until they choose one: the
 * store's default language is used then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_members', static function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('is_active')->comment('The language the store writes to them in, such as "fr"; null for the store\'s default language.');
        });
    }

    public function down(): void
    {
        Schema::table('staff_members', static function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
