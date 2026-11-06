<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Storefront content (Tenant\Storefront)
|--------------------------------------------------------------------------
|
| The defaults a new store's storefront settings start with, and the limits
| of the content staff write. Each store's own settings live in its
| storefront_settings row, created with the store; reads never fill it in.
|
*/

return [

    // How the storefront titles a page for search engines and browser tabs: "{title}" is the page's own title and "{store}" the store's name.
    'seo_title_pattern' => env('STOREFRONT_SEO_TITLE_PATTERN', '{title} · {store}'),

    // Whether search engines may list the storefront and follow its links: one of App\Tenant\Storefront\Enums\RobotsDirective.
    'robots' => env('STOREFRONT_ROBOTS', 'index_follow'),

    // How long the storefront's public content is kept in the cache, in seconds. Every change to it is seen at once regardless.
    'cache_seconds' => (int) env('STOREFRONT_CACHE_SECONDS', 3600),

    // Where on the storefront an image banner may be placed. Storefront themes show each place's banners in their order; removing a place here hides its banners without deleting them.
    'banner_positions' => ['home_hero', 'home_secondary', 'catalog_top'],

    // The most image banners shoppers get for one place, and the most announcements at once, in their order.
    'banners_per_position' => 10,
    'announcements_shown' => 3,

];
