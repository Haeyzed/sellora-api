<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Catalog
|--------------------------------------------------------------------------
|
| Limits on how a store's catalog can be shaped.
|
*/

return [

    // How many levels categories can nest, counting top-level categories as level 1 ("Clothing > Men > Shirts" is 3).
    'category_max_depth' => 5,

    // How many attributes a product's variants can differ by, such as Size, Colour and Material.
    'max_options_per_product' => 3,

    // How many variants outside the trash one product can have.
    'max_variants_per_product' => 100,

    // How many products the storefront can fetch by ID at once, for comparing them and for the shopper's recently viewed list.
    'max_products_fetched' => 50,

    // How many products a merchant can link to one product as each kind of link: related products, upsells and cross-sells.
    'max_links_per_type' => 20,

    // Similar products shown beside a product: the merchant's related links, then the same category, the same brand and a similar price.
    'similar_products' => [
        // How many are shown.
        'max_shown' => 12,
        // How many candidates are kept per product shown, so enough remain once those sold out right now are left out.
        'candidates_per_shown' => 4,
        // How far a price may be from the product's lowest price, either way, in percent.
        'price_band_percent' => 25,
        // How long the list of candidates is kept; prices and availability are always read live.
        'cache_minutes' => 15,
    ],

    // The storefront's best sellers: the products that sold the most units recently (orders.best_sellers_period_days), from paid orders only.
    'best_sellers' => [
        // How many are shown.
        'max_shown' => 12,
        // How many candidates are kept per product shown, so enough remain once those that can't be bought right now are left out.
        'candidates_per_shown' => 4,
        // How long the list is kept per store and category; prices and availability are always read live.
        'cache_minutes' => 60,
    ],

];
