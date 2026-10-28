<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\StorageLimit;
use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use LogicException;

/**
 * Keeps a delivery's proof images with it, on the store's private disk, inside the transaction that marks it delivered.
 *
 * The plan's storage is checked under its lock, so two uploads can't both
 * take the last space; the lock is taken last, after every row lock of the
 * delivery, so it never waits while holding them in another order. The
 * files are stored before the transaction commits; if it rolls back, the
 * media rows go with it and the files are deleted, so no "delivered" is ever
 * saved without its proof and no file is left behind without a delivery.
 */
final readonly class ProofOfDeliveryStore
{
    public function __construct(
        private StorageLimit $storageLimit,
        private DatabaseManager $databases,
        private FilesystemFactory $filesystems,
    ) {}

    /**
     * @throws UsageLimitReachedException When the files would take the store over its plan's storage.
     * @throws LogicException When called outside a transaction.
     */
    public function keep(Delivery $delivery, ProofOfDelivery $proof): void
    {
        $this->storageLimit->ensureRoomFor($proof->size());

        foreach ($proof->files as $file) {
            $media = $delivery->addMedia($file->path)
                ->usingName($file->kind->value)
                ->usingFileName($file->fileName())
                ->toMediaCollection($file->kind->collection(), ProofOfDeliveryImages::disk());
            $directory = dirname($media->getPathRelativeToRoot());

            $this->databases->connection()->afterRollBack(fn () => $this->filesystems->disk($media->disk)->deleteDirectory($directory));
        }
    }
}
