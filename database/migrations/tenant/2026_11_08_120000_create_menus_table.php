<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Storefront menus (Step 19a): the store's navigation, such as its main menu
 * and its footer, each found by a key the storefront asks for. A menu holds
 * links two levels deep. Each link goes to exactly one thing: a page, a
 * category, a brand, a product or an address. A child always belongs to
 * its parent's menu, which a foreign key on both columns guarantees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', static function (Blueprint $table): void {
            $table->comment('The store\'s storefront menus, such as its main menu and its footer.');
            $table->id();
            $table->ulid('public_id')->unique()->comment('Public ID (ULID) used in the API; the numeric id never leaves the database.');
            $table->string('key', 64)->unique()->comment('What the storefront asks for the menu by, such as "main" or "footer"; lower-case letters, numbers and single hyphens.');
            $table->string('name', 120)->comment('The menu\'s name for staff; shoppers never see it.');
            $table->timestampsTz();
        });

        DB::statement("alter table menus add constraint menus_key_format check (key ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");

        Schema::create('menu_items', static function (Blueprint $table): void {
            $table->comment('The links in a storefront menu, two levels deep, each going to exactly one page, category, brand, product or address.');
            $table->id();
            $table->foreignId('menu_id')->comment('The menu it belongs to.')->constrained('menus')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->comment('The link it sits under, in the same menu; null at the top level.');
            $table->jsonb('label')->comment('By language: the text shoppers see; always includes the store\'s default language.');
            $table->foreignId('page_id')->nullable()->comment('The page it goes to, shown only while the page is published.')->constrained('pages')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->comment('The category it goes to, shown only while the category is outside the trash.')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->comment('The brand it goes to, shown only while the brand is outside the trash.')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->comment('The product it goes to, shown only while shoppers can see the product.')->constrained('products')->cascadeOnDelete();
            $table->string('url', 2048)->nullable()->comment('The address it goes to: https, mailto:, tel: or a path on the storefront.');
            $table->boolean('opens_in_new_tab')->default(false)->comment('Whether the storefront opens it in a new tab.');
            $table->unsignedSmallInteger('position')->comment('Its place among its siblings, from 0.');
            $table->timestampsTz();

            $table->index(['menu_id', 'parent_id', 'position']);
        });

        DB::statement('alter table menu_items add constraint menu_items_menu_id_id_unique unique (menu_id, id)');
        DB::statement(<<<'SQL'
            alter table menu_items
                add constraint menu_items_parent_in_same_menu foreign key (menu_id, parent_id) references menu_items (menu_id, id) on delete cascade,
                add constraint menu_items_one_destination check (num_nonnulls(page_id, category_id, brand_id, product_id, url) = 1),
                add constraint menu_items_url_format check (url is null or url ~ '^(https://|mailto:|tel:|/(?!/))'),
                add constraint menu_items_never_own_parent check (parent_id is null or parent_id <> id),
                add constraint menu_items_label_translated check (jsonb_typeof(label) = 'object' and label <> '{}'::jsonb)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
