<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Storefront banners (Step 19a): image banners placed somewhere on the
 * storefront (the places come from config storefront.banner_positions), and
 * announcements, the short messages in a bar along the top. Either may run
 * from and until a time, in UTC, and be switched off. Their texts are in the
 * store's languages; an image banner's image is on the public disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', static function (Blueprint $table): void {
            $table->comment('The storefront\'s image banners and announcements, each shown while active and within its times.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->string('kind', 16)->comment('One of App\Tenant\Storefront\Enums\BannerKind; never changes.');
            $table->string('position', 64)->nullable()->comment('Where an image banner is placed, one of config storefront.banner_positions; null for an announcement.');
            $table->jsonb('title')->comment('By language: an image banner\'s headline (and its image\'s description), or an announcement\'s message.');
            $table->jsonb('body')->nullable()->comment('By language: a line of text under the headline.');
            $table->string('link_url', 2048)->nullable()->comment('Where it leads: https, mailto:, tel: or a path on the storefront.');
            $table->jsonb('link_label')->nullable()->comment('By language: the text of its button; only with a link.');
            $table->char('text_color', 7)->nullable()->comment('Its text colour, such as "#ffffff"; the theme\'s when null.');
            $table->char('background_color', 7)->nullable()->comment('Its background colour, such as "#1a1a1a"; the theme\'s when null.');
            $table->timestampTz('starts_at')->nullable()->comment('When it starts being shown; at once when null.');
            $table->timestampTz('ends_at')->nullable()->comment('When it stops being shown (not included); never when null.');
            $table->boolean('is_active')->default(true)->comment('Whether staff have it switched on; shown only while on and within its times.');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Its order among the banners in its place, or among announcements, lowest first.');
            $table->timestampsTz();

            $table->index(['kind', 'position', 'sort_order']);
        });

        DB::statement(<<<'SQL'
            alter table banners
                add constraint banners_kind_check check (kind in ('image', 'announcement')),
                add constraint banners_position_for_images_only check ((kind = 'image' and position is not null) or (kind = 'announcement' and position is null)),
                add constraint banners_title_translated check (jsonb_typeof(title) = 'object' and title <> '{}'::jsonb),
                add constraint banners_body_translated check (body is null or jsonb_typeof(body) = 'object'),
                add constraint banners_link_label_needs_link check (link_label is null or (link_url is not null and jsonb_typeof(link_label) = 'object')),
                add constraint banners_link_url_format check (link_url is null or link_url ~ '^(https://|mailto:|tel:|/(?!/))'),
                add constraint banners_text_color_format check (text_color is null or text_color ~ '^#[0-9a-f]{6}$'),
                add constraint banners_background_color_format check (background_color is null or background_color ~ '^#[0-9a-f]{6}$'),
                add constraint banners_ends_after_start check (starts_at is null or ends_at is null or ends_at > starts_at)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
