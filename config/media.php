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
| They go on the "public" disk, served on the store's own domain, until a
| public S3 disk (ideally behind a CDN) is set up: then set
| STOREFRONT_IMAGE_DISK=s3. Each store's files are kept apart either way
| (config/tenancy.php). Tests always use "public" (phpunit.xml).
|
*/

return [

    'storefront_images' => [
        'disk' => env('STOREFRONT_IMAGE_DISK', 'public'),
        'types' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_kilobytes' => 10 * 1024,
        'max_width' => 6000,
        'max_height' => 6000,
    ],

    /*
    | Proof of delivery: the photo a driver takes of the handed-over goods
    | and the recipient's signature. Personal data, so they stay on the
    | store's private disk and are only ever streamed to staff. Same types as
    | storefront images, smaller limits. Each file is re-encoded on upload,
    | which drops its metadata (camera, GPS position, time taken).
    */
    'proof_of_delivery' => [
        'disk' => env('PROOF_OF_DELIVERY_DISK', 'local'),
        'types' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_kilobytes' => 5 * 1024,
        'max_width' => 4096,
        'max_height' => 4096,
    ],

];
