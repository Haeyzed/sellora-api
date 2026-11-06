<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Search engine texts for products, categories and brands (Step 19a): a
 * title and a description in the store's languages, for when they should
 * differ from the name and the description shoppers read.
 */
return new class extends Migration
{
    private const array TABLES = ['products', 'categories', 'brands'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->jsonb('seo_title')->nullable()->comment('By language: the title search engines show, when it differs from the name.');
                $table->jsonb('seo_description')->nullable()->comment('By language: the description search engines show.');
            });

            DB::statement(<<<SQL
                alter table {$tableName}
                    add constraint {$tableName}_seo_title_translated check (seo_title is null or jsonb_typeof(seo_title) = 'object'),
                    add constraint {$tableName}_seo_description_translated check (seo_description is null or jsonb_typeof(seo_description) = 'object')
                SQL);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropColumn(['seo_title', 'seo_description']);
            });
        }
    }
};
