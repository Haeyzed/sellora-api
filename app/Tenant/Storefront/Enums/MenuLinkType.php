<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Enums;

/**
 * Where a menu link goes: one of the store's pages, categories, brands or products, or an address typed by staff.
 */
enum MenuLinkType: string
{
    case Page = 'page';
    case Category = 'category';
    case Brand = 'brand';
    case Product = 'product';
    case Url = 'url';

    /**
     * The menu_items column that holds this kind of destination.
     */
    public function column(): string
    {
        return match ($this) {
            self::Page => 'page_id',
            self::Category => 'category_id',
            self::Brand => 'brand_id',
            self::Product => 'product_id',
            self::Url => 'url',
        };
    }
}
