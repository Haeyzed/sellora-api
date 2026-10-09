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

];
