<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Which social sign-in providers the store offers its customers (Step 10b):
 * none until the store chooses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->jsonb('social_sign_in_providers')->default('[]')->comment('Providers offered to customers, such as ["google", "apple"]');
        });

        DB::statement("alter table store_settings add constraint store_settings_social_sign_in_providers_check check (jsonb_typeof(social_sign_in_providers) = 'array')");
    }

    public function down(): void
    {
        Schema::table('store_settings', static function (Blueprint $table): void {
            $table->dropColumn('social_sign_in_providers');
        });
    }
};
