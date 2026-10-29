<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Services;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\StorageLimit;
use App\Shared\Media\EncodedImage;
use App\Shared\Media\PrivateImages;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use LogicException;

/**
 * Keeps a return's photos with it, on the store's private disk, inside the transaction that saves the return.
 *
 * Each photo was re-encoded without its metadata (Shared PrivateImages,
 * config media.return_photos). The plan's storage is checked under its lock,
 * taken last, after the return's own locks. The files are stored before the
 * transaction commits; if it rolls back, the media rows go with it and the
 * files are deleted, so no file is left behind without a return.
 */
final readonly class ReturnPhotos
{
    public const string KIND = 'return_photos';

    public const string COLLECTION = 'photos';

    public function __construct(
        private StorageLimit $storageLimit,
        private DatabaseManager $databases,
        private FilesystemFactory $filesystems,
    ) {}

    /**
     * @param  list<EncodedImage>  $photos
     *
     * @throws UsageLimitReachedException When the photos would take the store over its plan's storage.
     * @throws LogicException When called outside a transaction.
     */
    public function keep(OrderReturn $return, array $photos): void
    {
        if ($photos === []) {
            return;
        }

        $this->storageLimit->ensureRoomFor(array_sum(array_map(static fn (EncodedImage $photo): int => $photo->size, $photos)));

        foreach ($photos as $index => $photo) {
            $media = $return->addMedia($photo->path)
                ->usingName('photo-'.($index + 1))
                ->usingFileName('photo-'.($index + 1).'.'.$photo->extension)
                ->toMediaCollection(self::COLLECTION, PrivateImages::disk(self::KIND));
            $directory = dirname($media->getPathRelativeToRoot());

            $this->databases->connection()->afterRollBack(fn () => $this->filesystems->disk($media->disk)->deleteDirectory($directory));
        }
    }

    /**
     * Deletes temporary files that were never kept, such as when the request was refused.
     *
     * @param  list<EncodedImage>  $photos
     */
    public static function discard(array $photos): void
    {
        foreach ($photos as $photo) {
            if (is_file($photo->path)) {
                unlink($photo->path);
            }
        }
    }
}
