<?php

declare(strict_types=1);

namespace App\Shared\Media;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\StorageLimit;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Keeps an uploaded storefront image for a model, on the public disk, once the plan's storage has room for it.
 *
 * The file is checked against the storage limit under a lock, so two uploads
 * can't both take the last space. Its width and height are kept with it for
 * clients laying out pages. Resized copies are made on the queue.
 */
final readonly class StorefrontImageUploader
{
    public function __construct(
        private StorageLimit $storageLimit,
        private DatabaseManager $databases,
    ) {}

    /**
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     */
    public function add(HasMedia $model, UploadedFile $image, string $collection): Media
    {
        return $this->databases->connection()->transaction(function () use ($model, $image, $collection): Media {
            $this->storageLimit->ensureRoomFor($image->getSize() ?: 0);
            $dimensions = getimagesize($image->getRealPath()) ?: [null, null];

            return $model->addMedia($image)
                ->withCustomProperties(['width' => $dimensions[0], 'height' => $dimensions[1]])
                ->toMediaCollection($collection, StorefrontImage::DISK);
        });
    }
}
