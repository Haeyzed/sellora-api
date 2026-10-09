<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Storefront images
|--------------------------------------------------------------------------
|
| Product, variant, category and brand images and the store logo: the only
| uploads kept on a public disk (section 14). JPEG, PNG and WebP only, never
| SVG (it can carry scripts). Dimensions are capped as well as file size,
| because a small compressed file can still decode to an enormous image.
|
*/

return [

    'storefront_images' => [
        'types' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_kilobytes' => 10 * 1024,
        'max_width' => 6000,
        'max_height' => 6000,
    ],

];
