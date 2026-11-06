<?php

declare(strict_types=1);

namespace App\Tenant\Storefront;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How the storefront's content and settings appear in a store export. Nothing in them is a secret or personal data.
 */
final class StorefrontStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('storefront_settings', include: [
            'id', 'seo_title_pattern', 'seo_description', 'robots', 'privacy_page_id', 'terms_page_id', 'refunds_page_id', 'shipping_page_id', 'created_at', 'updated_at',
        ]);

        $registry->table('pages', include: [
            'id', 'public_id', 'title', 'slug', 'body', 'seo_title', 'seo_description', 'status', 'published_at', 'is_homepage', 'created_at', 'updated_at', 'deleted_at',
        ]);

        $registry->table('menus', include: [
            'id', 'public_id', 'key', 'name', 'created_at', 'updated_at',
        ]);

        $registry->table('menu_items', include: [
            'id', 'menu_id', 'parent_id', 'label', 'page_id', 'category_id', 'brand_id', 'product_id', 'url', 'opens_in_new_tab', 'position', 'created_at', 'updated_at',
        ]);
    }
}
