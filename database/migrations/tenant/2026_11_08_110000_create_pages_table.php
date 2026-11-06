<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Storefront pages (Step 19a): the store's own pages, such as "About us" or
 * its returns policy, written in its languages and published when ready.
 * Their bodies are cleaned HTML. At most one page outside the trash is the
 * homepage. A slug is unique among pages outside the trash. The storefront
 * settings say which page serves each policy (privacy, terms, refunds,
 * shipping); core writes none of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', static function (Blueprint $table): void {
            $table->comment('The store\'s own storefront pages, such as "About us", in its languages; shoppers see only published ones.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->jsonb('title')->comment('By language; always includes the store\'s default language.');
            $table->string('slug', 190)->comment('Used in the page\'s storefront address, such as "about-us"; unique among pages outside the trash.');
            $table->jsonb('body')->nullable()->comment('By language: cleaned HTML (App\Tenant\Storefront\Services\PageHtml), cleaned again when served.');
            $table->jsonb('seo_title')->nullable()->comment('By language: the title search engines show, when it differs from the page\'s title.');
            $table->jsonb('seo_description')->nullable()->comment('By language: the description search engines show.');
            $table->string('status', 16)->default('draft')->comment('One of App\Tenant\Storefront\Enums\PageStatus.');
            $table->timestampTz('published_at')->nullable()->comment('When it was first published; kept when it is unpublished.');
            $table->boolean('is_homepage')->default(false)->comment('Whether the storefront opens on this page; at most one page outside the trash.');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('create unique index pages_slug_unique on pages (slug) where deleted_at is null');
        DB::statement('create unique index pages_one_homepage on pages (is_homepage) where is_homepage and deleted_at is null');
        DB::statement(<<<'SQL'
            alter table pages
                add constraint pages_slug_format check (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
                add constraint pages_status_check check (status in ('draft', 'published')),
                add constraint pages_published check (status <> 'published' or published_at is not null),
                add constraint pages_title_translated check (jsonb_typeof(title) = 'object' and title <> '{}'::jsonb),
                add constraint pages_body_translated check (body is null or jsonb_typeof(body) = 'object'),
                add constraint pages_seo_title_translated check (seo_title is null or jsonb_typeof(seo_title) = 'object'),
                add constraint pages_seo_description_translated check (seo_description is null or jsonb_typeof(seo_description) = 'object'),
                add constraint pages_trashed_never_homepage check (deleted_at is null or not is_homepage)
            SQL);

        Schema::table('storefront_settings', static function (Blueprint $table): void {
            $table->foreignId('privacy_page_id')->nullable()->comment('The page with the store\'s privacy policy; null when none is chosen.')->constrained('pages')->restrictOnDelete();
            $table->foreignId('terms_page_id')->nullable()->comment('The page with the store\'s terms and conditions; null when none is chosen.')->constrained('pages')->restrictOnDelete();
            $table->foreignId('refunds_page_id')->nullable()->comment('The page with the store\'s refund and returns policy; null when none is chosen.')->constrained('pages')->restrictOnDelete();
            $table->foreignId('shipping_page_id')->nullable()->comment('The page with the store\'s shipping policy; null when none is chosen.')->constrained('pages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('privacy_page_id');
            $table->dropConstrainedForeignId('terms_page_id');
            $table->dropConstrainedForeignId('refunds_page_id');
            $table->dropConstrainedForeignId('shipping_page_id');
        });
        Schema::dropIfExists('pages');
    }
};
