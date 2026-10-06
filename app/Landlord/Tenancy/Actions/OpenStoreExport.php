<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Shared\Auth\AccountReference;
use App\Shared\Tenancy\Exceptions\StoreExportNotReadyException;
use App\Shared\Tenancy\StoreExportDownload;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use RuntimeException;
use Spatie\Activitylog\Support\ActivityLogger;

/**
 * Opens a ready export for the account that requested it to download, and records every download.
 */
final readonly class OpenStoreExport
{
    public function __construct(private FilesystemFactory $filesystems) {}

    /**
     * @param  StoreExport  $storeExport  Already checked to be the requester's, with FindRequestedStoreExport.
     * @param  PlatformAdmin|null  $platformAdmin  The platform admin downloading, or null when the owner does.
     *
     * @throws StoreExportNotReadyException When it is still being built, failed, or has expired.
     */
    public function handle(StoreExport $storeExport, AccountReference $requester, ?PlatformAdmin $platformAdmin = null): StoreExportDownload
    {
        if (! $storeExport->isDownloadable() || $storeExport->path === null) {
            throw new StoreExportNotReadyException;
        }

        // Never the owner as causer: they live in the store's database, so the central log keeps them in downloaded_by instead.
        activity('stores')
            ->causedBy($platformAdmin)
            ->when($platformAdmin === null, static fn (ActivityLogger $activity): ActivityLogger => $activity->causedByAnonymous())
            ->performedOn($storeExport->tenant)
            ->event('store_export_downloaded')
            ->withProperties(['export' => $storeExport->public_id, 'downloaded_by' => ['type' => $requester->type, 'id' => $requester->publicId]])
            ->log('Downloaded an export of the store');

        $disk = $this->filesystems->disk($storeExport->disk);
        $path = $storeExport->path;

        return new StoreExportDownload(
            "store-export-{$storeExport->public_id}.zip",
            $storeExport->size_bytes,
            static fn () => $disk->readStream($path) ?? throw new RuntimeException("The export file {$path} is missing."),
        );
    }
}
