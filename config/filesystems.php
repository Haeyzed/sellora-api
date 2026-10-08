<?php

declare(strict_types=1);

/*
 * A private disk for full store exports in one hosting region (see
 * tenancy.store_exports.disks). STORE_EXPORT_<REGION>_DRIVER chooses "local"
 * (the default, under storage/app/store-exports/<region>) or "s3", a bucket
 * in that region set by the other STORE_EXPORT_<REGION>_* variables. Never
 * served by URL, and not suffixed per store by tenancy (the exports are
 * written by platform-side jobs).
 */
$storeExportDisk = static function (string $region): array {
    $prefix = 'STORE_EXPORT_'.strtoupper($region).'_';

    if (env($prefix.'DRIVER', 'local') === 's3') {
        return [
            'driver' => 's3',
            'key' => env($prefix.'KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env($prefix.'SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env($prefix.'BUCKET_REGION'),
            'bucket' => env($prefix.'BUCKET'),
            'endpoint' => env($prefix.'ENDPOINT'),
            'use_path_style_endpoint' => env($prefix.'USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ];
    }

    return [
        'driver' => 'local',
        'root' => env($prefix.'ROOT', storage_path("app/store-exports/{$region}")),
        'visibility' => 'private',
        'serve' => false,
        'throw' => true,
        'report' => false,
    ];
};

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Full store exports on a developer's machine: private, never served by URL, and not
        // suffixed per store by tenancy (the exports are written by platform-side jobs).
        'store_exports' => [
            'driver' => 'local',
            'root' => storage_path('app/store-exports'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        'store_exports_africa' => $storeExportDisk('africa'),
        'store_exports_eu' => $storeExportDisk('eu'),
        'store_exports_us' => $storeExportDisk('us'),

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
