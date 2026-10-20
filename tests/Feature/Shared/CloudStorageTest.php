<?php

declare(strict_types=1);

/*
 * Tests never reach real cloud storage. phpunit.xml forces every disk the app
 * picks from the environment to a local one and blanks every cloud
 * credential, whatever .env or the shell says; TestCase then swaps any disk
 * that still isn't local. Both layers are checked here, so a new disk or
 * setting can't quietly point a test run at a real bucket.
 */

it('keeps every disk local in tests', function (): void {
    /** @var array<string, array<string, mixed>> $disks */
    $disks = config()->array('filesystems.disks');

    foreach ($disks as $name => $disk) {
        expect($disk['driver'] ?? null)->toBe('local', "Disk {$name} isn't local in tests.")
            ->and(array_diff(array_keys($disk), ['driver', 'root', 'url', 'visibility', 'serve', 'throw', 'report', 'permissions', 'links', 'lock']))->toBe([], "Disk {$name} carries cloud settings in tests.");
    }
});

it('sets no cloud credentials and picks only local disks from the environment in tests', function (): void {
    foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_SESSION_TOKEN', 'AWS_BUCKET', 'AWS_URL', 'AWS_ENDPOINT'] as $variable) {
        expect(env($variable))->toBeEmpty("{$variable} is set in tests.");
    }

    // The disks as the environment alone configures them, before TestCase steps in.
    /** @var array{default: string, disks: array<string, array<string, mixed>>} $filesystems */
    $filesystems = require config_path('filesystems.php');

    foreach ($filesystems['disks'] as $name => $disk) {
        expect(array_filter(array_intersect_key($disk, array_flip(['key', 'secret', 'token', 'bucket', 'endpoint']))))->toBe([], "Disk {$name} has cloud settings from the environment in tests.");
    }

    $chosen = [
        'filesystems.default' => $filesystems['default'],
        'media.storefront_images.disk' => config()->string('media.storefront_images.disk'),
        'media-library.disk_name' => config()->string('media-library.disk_name'),
        'customers.data_exports.disk' => config()->string('customers.data_exports.disk'),
        'tenancy.store_exports.default_disk' => config()->string('tenancy.store_exports.default_disk'),
    ];

    foreach (config()->array('tenancy.store_exports.disks') as $region => $diskName) {
        $chosen["tenancy.store_exports.disks.{$region}"] = $diskName;
    }

    foreach ($chosen as $setting => $diskName) {
        expect($filesystems['disks'][$diskName]['driver'] ?? null)->toBe('local', "{$setting} picks disk {$diskName}, which isn't local in tests.");
    }
});
