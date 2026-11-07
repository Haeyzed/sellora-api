<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A platform admin's preferred language (Step 20a), for what the platform
 * sends them outside a request (platform alerts from the queue or the
 * scheduler). Null until they choose one: the platform's default language is
 * used then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_admins', static function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('is_active')->comment('The language the platform writes to them in, such as "fr"; null for the platform\'s default language.');
        });
    }

    public function down(): void
    {
        Schema::table('platform_admins', static function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
