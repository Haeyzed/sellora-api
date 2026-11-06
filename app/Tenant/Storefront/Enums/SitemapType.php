<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Enums;

/**
 * Which of the storefront's addresses a part of its sitemap lists.
 */
enum SitemapType: string
{
    case Pages = 'pages';
    case Products = 'products';
    case Categories = 'categories';
    case Brands = 'brands';
}
