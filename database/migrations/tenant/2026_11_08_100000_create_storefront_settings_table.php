<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Storefront settings (Step 19a): how the storefront presents itself to
 * search engines and social sites. One row, created with every store (this
 * migration gives existing stores theirs, from the defaults in
 * config/storefront.php); reads never fill it in (CLAUDE.md section 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', static function (Blueprint $table): void {
            $table->comment('How the storefront presents itself to search engines and social sites. One row, created with the store and never missing.');
            $table->smallInteger('id')->primary();
            $table->string('seo_title_pattern', 120)->comment('How a page is titled for search engines: {title} is the page\'s own title and {store} the store\'s name.');
            $table->jsonb('seo_description')->nullable()->comment('The description search engines show for pages without their own, by language.');
            $table->string('robots', 24)->comment('Whether search engines may list the storefront and follow its links: one of App\Tenant\Storefront\Enums\RobotsDirective.');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            alter table storefront_settings
                add constraint storefront_settings_single_row check (id = 1),
                add constraint storefront_settings_title_has_title check (position('{title}' in seo_title_pattern) > 0),
                add constraint storefront_settings_robots_check check (robots in ('index_follow', 'noindex_follow', 'noindex_nofollow')),
                add constraint storefront_settings_description_translated check (seo_description is null or jsonb_typeof(seo_description) = 'object')
            SQL);

        $now = CarbonImmutable::now();

        DB::table('storefront_settings')->insert([
            'id' => 1,
            'seo_title_pattern' => config()->string('storefront.seo_title_pattern'),
            'robots' => config()->string('storefront.robots'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
    }
};
