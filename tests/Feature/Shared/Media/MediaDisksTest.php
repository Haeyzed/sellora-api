<?php

declare(strict_types=1);

use App\Shared\Media\StorefrontImage;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

/*
 * Section 14: only images meant for the storefront go on a public disk;
 * proof of delivery, return photos, tax documents and both kinds of export
 * stay private. The uploaders alone decide where a file goes, so no media
 * collection names a disk of its own, and the library's fallback disk (for
 * an uploader that forgot to name one) is a private one.
 */

it('lets no media collection choose its own disk, so the uploaders alone decide', function (): void {
    $models = array_filter(Relation::morphMap(), static fn (string $class): bool => is_subclass_of($class, HasMedia::class));

    expect($models)->not->toBeEmpty();

    foreach ($models as $alias => $class) {
        $model = new $class;
        $model->registerMediaCollections();

        foreach ($model->mediaCollections as $collection) {
            expect($collection)->toBeInstanceOf(MediaCollection::class)
                ->and($collection->diskName)->toBe('', "The {$alias} collection \"{$collection->name}\" names its own disk");
        }
    }
});

it('keeps storefront images on a public disk and every other kind of file on a private one', function (): void {
    $isPublic = static fn (string $disk): bool => config("filesystems.disks.{$disk}.visibility") === 'public';
    $storefrontDisk = StorefrontImage::disk();

    $privateDisks = [
        'proof of delivery' => config()->string('media.proof_of_delivery.disk'),
        'return photos' => config()->string('media.return_photos.disk'),
        'tax documents' => config()->string('orders.documents_disk'),
        'customer data exports' => config()->string('customers.data_exports.disk'),
        'store exports' => config()->string('tenancy.store_exports.default_disk'),
        'the media fallback' => config()->string('media-library.disk_name'),
        ...array_map(static fn (string $disk): string => $disk, config()->array('tenancy.store_exports.disks')),
    ];

    expect($isPublic($storefrontDisk))->toBeTrue();

    foreach ($privateDisks as $kind => $disk) {
        expect(config("filesystems.disks.{$disk}"))->toBeArray("No disk {$disk} for {$kind}")
            ->and($isPublic($disk))->toBeFalse("{$kind} is on the public disk {$disk}")
            ->and($disk)->not->toBe($storefrontDisk, "{$kind} shares the storefront images' disk");
    }
});
