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

];
