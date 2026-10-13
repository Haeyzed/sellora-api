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
            // Granted by staff only, after checking the customer's tax ID.
            $table->boolean('is_tax_exempt')->default(false)->after('locale');
            // Entered by the customer: upper case, without spaces, dots or dashes.
            $table->string('tax_id', 32)->nullable()->after('is_tax_exempt');
            $table->char('tax_id_country', 2)->nullable()->after('tax_id');
        });

        DB::statement('alter table customers add constraint customers_tax_id_with_country_check check ((tax_id is null) = (tax_id_country is null))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('alter table customers drop constraint if exists customers_tax_id_with_country_check');

        Schema::table('customers', static function (Blueprint $table): void {
            $table->dropColumn(['is_tax_exempt', 'tax_id', 'tax_id_country']);
        });
    }
};
